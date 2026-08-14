<?php
/*
 * -------------------------------------------------------------------------
 *  GLPI Plugin "Campos Dinâmicos Formulário" (dynamicfields)
 *  -------------------------------------------------------------------------
 *  Helper class: shared utilities for the plugin.
 *
 *  Responsibilities:
 *   - List the compatible "source questions" of a form (questions of type
 *     "GLPI object" or "Dropdown" placed before the current one).
 *   - List the displayable attributes of an itemtype (raw columns of its
 *     main table, excluding sensitive columns such as passwords/tokens).
 *   - Resolve the value of an attribute for a given item (the runtime
 *     computation used by front/ajax.php?action=get_field_value).
 *  -------------------------------------------------------------------------
 *  LICENSE: GPL-2.0+
 *  -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginDynamicfieldsHelper extends CommonDBTM
{
   /**
    * Column names that must never be exposed as "attribute to display"
    * (credentials, tokens, ...).
    *
    * @var array
    */
   const SENSITIVE_COLUMNS = [
      'passwd',
      'password',
      'api_token',
      'cookie_token',
      'session_token',
      'glpiauthsecret',
      'pin',
   ];

   /**
    * Column names that are never useful to display (technical/system).
    *
    * @var array
    */
   const SYSTEM_COLUMNS = [
      'id',
      'entities_id',
      'is_recursive',
      'is_deleted',
      'is_template',
      'template',
   ];

   /**
    * Question fieldtypes that can be used as a source for a dynamic field.
    * "glpiselect" = GLPI object, "dropdown" = Dropdown.
    *
    * @var array
    */
   const SOURCE_FIELDTYPES = ['glpiselect', 'dropdown'];

   /**
    * Make an AJAX GET request (interface parity with PluginFormcreatorCommon,
    * kept for convenience).
    *
    * @param string $url       absolute URL to the ajax endpoint
    * @param array  $params    query string parameters
    * @param bool   $progressive_loading ignored, kept for interface parity
    * @return array \{'content' => string, 'http_error' => int, 'title' => string\}
    */
   public static function getAjaxComponent(string $url, array $params = [], bool $progressive_loading = false): array {
      $args = '';
      if (count($params) > 0) {
         $args = '?';
         $args .= implode(
            '&',
            array_map(
               function ($key) use ($params) {
                  return $key . '=' . rawurlencode($params[$key]);
               },
               array_keys($params)
            )
         );
      }

      $content  = "";
      $httpCode = 0;
      if (isset($_SESSION['glpiID'])) {
         $content = Html::clean(
            Ajax::getUrl('', $url . $args, urldecode($url))
         );
         $httpCode = 200;
      } else {
         $opts = [
            'http' => [
               'ignore_errors' => true,
               'method'        => 'GET',
            ],
         ];
         $ctx = stream_context_create($opts);
         $content = @file_get_contents($url . $args, false, $ctx);
         if (isset($http_response_header[0])) {
            if (preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
               $httpCode = (int)$m[1];
            }
         }
         if ($httpCode === 0) {
            $httpCode = 200;
         }
      }

      return [
         'content'    => (string)$content,
         'http_error' => $httpCode,
         'title'      => "",
      ];
   }

   /**
    * Get the list of questions of the same form that can be used as the
    * source of a dynamic field. All questions of type "GLPI object" or
    * "Dropdown" of the form are returned, ordered by their visual position
    * in the form (section order, then row, then column).
    *
    * @param int $questionId id of the dynamic field question
    * @return array source_question_id => "[Itemtype] Question name"
    */
   public static function getCompatibleSourceQuestions(int $questionId, ?PluginFormcreatorQuestion $question = null): array {
      global $DB;

      $options = [];

      // When the question is not saved yet (questionId 0), the caller passes
      // the (unsaved) question object carrying its section; otherwise we load
      // the persisted question.
      if ($question === null) {
         $question = new PluginFormcreatorQuestion();
         if (!$question->getFromDB($questionId)) {
            return $options;
         }
      }

      // Find all the sections of the form the question belongs to
      $section = new PluginFormcreatorSection();
      if (!$section->getFromDB($question->fields['plugin_formcreator_sections_id'])) {
         return $options;
      }
      $sectionIds = [];
      $sections   = (new PluginFormcreatorSection())->getSectionsFromForm($section->fields['plugin_formcreator_forms_id']);
      $sectionOrder = [];
      foreach ($sections as $s) {
         $sectionIds[]           = $s->getID();
         $sectionOrder[$s->getID()] = count($sectionOrder);
      }
      if (count($sectionIds) === 0) {
         return $options;
      }

      $rows = $DB->request([
         'FROM'  => PluginFormcreatorQuestion::getTable(),
         'WHERE' => [
            'plugin_formcreator_sections_id' => $sectionIds,
            'fieldtype'                      => self::SOURCE_FIELDTYPES,
         ],
         'ORDER' => 'id ASC',
      ]);

      $candidates = [];
      foreach ($rows as $row) {
         $source = new PluginFormcreatorQuestion();
         $source->getFromDB($row['id']);
         $itemtype = self::getItemtypeForQuestion($source);
         if ($itemtype === '' || !class_exists($itemtype)) {
            continue;
         }
         if (!is_subclass_of($itemtype, CommonDBTM::class)) {
            continue;
         }
         $itemtypeLabel = call_user_func([$itemtype, 'getTypeName'], 1);
         $candidates[$row['id']] = [
            'label'        => sprintf('[%s] %s', $itemtypeLabel, $row['name']),
            'sectionOrder' => (int)($sectionOrder[$row['plugin_formcreator_sections_id']] ?? 0),
            'row'          => (int)($row['row'] ?? 0),
            'col'          => (int)($row['col'] ?? 0),
         ];
      }

      // Sort by visual position in the form: section order, then row, then column.
      uasort($candidates, static function (array $a, array $b): int {
         return [$a['sectionOrder'], $a['row'], $a['col']] <=> [$b['sectionOrder'], $b['row'], $b['col']];
      });

      foreach ($candidates as $id => $info) {
         $options[(string)$id] = $info['label'];
      }

      return $options;
   }

   /**
    * Get the itemtype managed by a formcreator question.
    *
    * @param PluginFormcreatorQuestion $question
    * @return string itemtype class name, empty string if none
    */
   public static function getItemtypeForQuestion(PluginFormcreatorQuestion $question): string {
      $itemtype = $question->fields['itemtype'] ?? '';
      if (is_array($itemtype)) {
         $itemtype = json_encode($itemtype);
      }
      if (is_string($itemtype) && strlen($itemtype) > 0 && $itemtype[0] === '{') {
         // Legacy storage: itemtype embedded in JSON (old "fields" type)
         $decoded = json_decode($itemtype, true);
         if (is_array($decoded) && isset($decoded['itemtype'])) {
            $itemtype = $decoded['itemtype'];
         }
      }
      $itemtype = trim((string)$itemtype);
      if ($itemtype === '' || !class_exists($itemtype)) {
         return '';
      }
      return $itemtype;
   }

   /**
    * Get the displayable attributes of an itemtype.
    *
    * Raw columns of the itemtype main table, excluding sensitive and system
    * columns. When the itemtype has no search options (or the itemtype is a
    * CommonDBTM without rawSearchOptions), falls back to the table columns.
    *
    * @param string $itemtype
    * @return array attribute => human readable label
    */
   public static function getAttributesForItemtype(string $itemtype): array {
      global $DB;

      if ($itemtype === '' || !class_exists($itemtype)) {
         return [];
      }
      if (!is_subclass_of($itemtype, CommonDBTM::class)) {
         return [];
      }

      $table      = $itemtype::getTable();
      $attributes = [];

      $instance = new $itemtype();
      if (method_exists($instance, 'getSearchOptions')) {
         foreach ($instance->getSearchOptions() as $option) {
            $field = $option['field'] ?? null;
            if ($field === null || $field === '') {
               continue;
            }
            // Only raw columns of the itemtype main table can be read back
            // directly from the item record (joined tables need a search).
            if (($option['table'] ?? $table) !== $table) {
               continue;
            }
            $datatype = (string)($option['datatype'] ?? '');
            if (in_array($datatype, ['password', 'file', 'files', 'image', 'passwd'])) {
               continue;
            }
            $field = strtolower($field);
            if (in_array($field, self::SENSITIVE_COLUMNS) || in_array($field, self::SYSTEM_COLUMNS)) {
               continue;
            }
            $attributes[$field] = (string)($option['name'] ?? $field);
         }
      }

      if (count($attributes) === 0 && $DB->tableExists($table)) {
         // Fallback: raw columns of the main table
         foreach ($DB->listFields($table) as $col => $spec) {
            $col = strtolower($col);
            if (in_array($col, self::SENSITIVE_COLUMNS) || in_array($col, self::SYSTEM_COLUMNS)) {
               continue;
            }
            $attributes[$col] = $col;
         }
      }

      ksort($attributes);
      return $attributes;
   }

   /**
    * Resolve the value of an attribute for a given item.
    *
    * @param string $itemtype
    * @param mixed  $itemsId   id of the item selected in the source question
    * @param string $attribute attribute name (column of the itemtype table)
    * @return array \{'value' => string, 'display' => string\}
    */
   public static function resolveAttributeValue(string $itemtype, $itemsId, string $attribute): array {
      $empty = ['value' => '', 'display' => ''];

      if ($itemtype === '' || !class_exists($itemtype)) {
         return $empty;
      }
      if (!is_subclass_of($itemtype, CommonDBTM::class)) {
         return $empty;
      }
      if ($itemsId === '' || $itemsId === null || $itemsId == '0') {
         return $empty;
      }

      $item    = new $itemtype();
      $itemsId = (int)$itemsId;
      if (!$item->getFromDB($itemsId)) {
         return $empty;
      }

      // Authorization: never resolve attributes of an item the current user
      // cannot view. items_id is fully attacker-controlled in front/ajax.php
      // (action=get_field_value), so without this check any authenticated user
      // could read attributes of arbitrary GLPI objects (IDOR).
      if (method_exists($item, 'can') && !$item->can($itemsId, READ)) {
         return $empty;
      }

      $attribute = strtolower(trim($attribute));
      if ($attribute === '') {
         return $empty;
      }

      // CommonTreeDropdown display the full name in a single column
      if ($item instanceof CommonTreeDropdown
          && in_array($attribute, ['name', 'completename'])
          && isset($item->fields['completename'])
      ) {
         $value = (string)$item->fields['completename'];
         return ['value' => $value, 'display' => $value];
      }

      if (isset($item->fields[$attribute])) {
         $value = $item->fields[$attribute];
         if (is_array($value)) {
            $value = json_encode($value);
         }
         $value = (string)$value;
         return ['value' => $value, 'display' => $value];
      }

      // Attribute is not a raw column (e.g. a computed search option):
      // resolve it through the GLPI search engine.
      return self::resolveViaSearchOption($item, $attribute);
   }

   /**
    * Resolve an attribute value through the GLPI search engine.
    * This is used for values that are not raw columns of the itemtype table
    * (e.g. the email of a user, which lives in another table).
    *
    * @param CommonDBTM $item
    * @param string     $attribute
    * @return array \{'value' => string, 'display' => string\}
    */
   private static function resolveViaSearchOption(CommonDBTM $item, string $attribute): array {
      $empty = ['value' => '', 'display' => ''];

      $searchOption = null;
      if (method_exists($item, 'getSearchOptionByField')) {
         $searchOption = $item->getSearchOptionByField('name', $attribute);
      }
      if ($searchOption === null || !is_array($searchOption) || !isset($searchOption['id'])) {
         return $empty;
      }

      $data = Search::prepareDatasForSearch(get_class($item), [
         'criteria' => [
            [
               'field'      => $searchOption['id'],
               'searchtype' => 'contains',
               'value'      => '',
            ],
            [
               'field'      => 2, // id
               'searchtype' => 'equals',
               'value'      => $item->getID(),
            ],
         ],
      ]);
      Search::constructSQL($data);
      Search::constructData($data);

      $values = [];
      if (isset($data['data']['rows'])) {
         foreach ($data['data']['rows'] as $row) {
            $targetKey = get_class($item) . '_' . $searchOption['id'];
            if (isset($row[$targetKey]) && is_array($row[$targetKey])) {
               for ($i = 0; $i < $row[$targetKey]['count']; $i++) {
                  $values[] = (string)$row[$targetKey][$i]['name'];
               }
            }
         }
      }
      $value = implode(', ', $values);
      return ['value' => $value, 'display' => $value];
   }

   /**
    * Absolute URL of the plugin AJAX endpoint.
    *
    * @return string
    */
   public static function getAjaxUrl(): string {
      global $CFG_GLPI;

      $url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/dynamicfields/front/ajax.php';

      // Carry the CSRF token in the query string so the designer/end-user AJAX
      // calls (front/ajax.php) can always be validated.
      //  - GLPI 11 stores the current token in $_SESSION['_glpi_csrf_token']
      //    and Html::checkAjaxInput() accepts it from the request.
      //  - GLPI 10 keeps its tokens in $_SESSION['glpicsrftokens'] (map of
      //    token => expiry) and does not attach them to AJAX GET requests;
      //    reuse the most recent valid token without consuming it.
      $token = '';
      if (isset($_SESSION['_glpi_csrf_token']) && !empty($_SESSION['_glpi_csrf_token'])) {
         $token = $_SESSION['_glpi_csrf_token'];
      } else if (isset($_SESSION['glpicsrftokens']) && is_array($_SESSION['glpicsrftokens']) && count($_SESSION['glpicsrftokens']) > 0) {
         $token = (string)array_key_last($_SESSION['glpicsrftokens']);
      }

      if ($token !== '') {
         $separator = (strpos($url, '?') === false) ? '?' : '&';
         $url .= $separator . '_glpi_csrf_token=' . rawurlencode($token);
      }

      return $url;
   }
}