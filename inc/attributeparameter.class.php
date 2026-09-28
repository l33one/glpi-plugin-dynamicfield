<?php
/*
 * -------------------------------------------------------------------------
 *  GLPI Plugin "Campos Dinâmicos Formulário" (dynamicfields)
 *  -------------------------------------------------------------------------
 *  Question parameter "attribute".
 *
 *  Lets the form designer pick which attribute (column of the itemtype of
 *  the source question) is displayed by the dynamic field.
 *
 *  Stored in glpi_plugin_dynamicfields_attributeparameters.
 *  -------------------------------------------------------------------------
 *  LICENSE: GPL-2.0+
 *  -------------------------------------------------------------------------
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Formcreator\Exception\ExportFailureException;
use GlpiPlugin\Formcreator\Exception\ImportFailureException;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginDynamicfieldsAttributeParameter
extends PluginFormcreatorAbstractQuestionParameter
{
   public static function getTypeName($nb = 0) {
      return _n('Dynamic field attribute', 'Dynamic field attributes', $nb, 'dynamicfields');
   }

   public function rawSearchOptions() {
      $tab = parent::rawSearchOptions();

      $tab[] = [
         'id'                 => '4',
         'table'              => $this::getTable(),
         'field'              => 'values',
         'name'               => __('Attribute', 'dynamicfields'),
         'datatype'           => 'text',
         'massiveaction'      => false,
      ];

      return $tab;
   }

   public function getParameterFormSize() {
      return 1;
   }

   public function getParameterForm(PluginFormcreatorQuestion $question) {
      // Get the currently selected source question (as saved in the DB)
      $sourceQuestionId = 0;
      $sourceParam = new PluginDynamicfieldsSourceQuestionParameter();
      $sourceParam->getFromDBByCrit([
         'plugin_formcreator_questions_id' => $question->getID(),
         'fieldname'                        => 'source_question',
      ]);
      if (!$sourceParam->isNewItem() && !empty($sourceParam->fields['values'])) {
         $sourceQuestionId = (int)$sourceParam->fields['values'];
      }

      // Get the currently selected attribute (as saved in the DB)
      $savedValue = '';
      $this->getFromDBByCrit([
         'plugin_formcreator_questions_id' => $question->getID(),
         'fieldname'                        => $this->fieldName,
      ]);
      if (!$this->isNewItem()) {
         $savedValue = (string)$this->fields['values'];
      }

      return self::renderForSource($question, $sourceQuestionId, $savedValue);
   }

   /**
    * Render the attribute parameter HTML dropdown.
    *
    * Shared between the designer form and the ajax endpoint
    * (action=get_attributes), which re-renders the dropdown when the
    * designer changes the source question.
    *
    * @param PluginFormcreatorQuestion $question
    * @param int                       $sourceQuestionId id of the selected source question
    * @param string                    $savedValue       currently saved attribute (if any)
    * @return string HTML
    */
   public static function renderForSource(PluginFormcreatorQuestion $question, int $sourceQuestionId, string $savedValue): string {
      $fieldTypeName = $question->fields['fieldtype'] ?? 'dynamic';
      $name = '_parameters[' . $fieldTypeName . '][attribute]';

      $attributes = [];
      if ($sourceQuestionId > 0) {
         $source = new PluginFormcreatorQuestion();
         if ($source->getFromDB($sourceQuestionId)) {
            $itemtype = PluginDynamicfieldsHelper::getItemtypeForQuestion($source);
            $attributes = PluginDynamicfieldsHelper::getAttributesForItemtype($itemtype);
         }
      }

      $message = '';
      if ($sourceQuestionId <= 0) {
         $message = __('Select a question of type "GLPI object" or "Dropdown" first.', 'dynamicfields');
      } else if (count($attributes) === 0) {
         $message = __('No previous compatible question found.', 'dynamicfields');
      }

      $out = TemplateRenderer::getInstance()->render(
         '@dynamicfields/questionparameter/dynamic_attribute.html.twig',
         [
            'name'        => $name,
            'value'       => $savedValue,
            'options'     => $attributes,
            'label'       => __('Attribute to display', 'dynamicfields'),
            'message'     => $message,
            'question_id' => $question->getID(),
         ]
      );
      return $out;
   }

   public function post_getEmpty() {
      $this->fields['values'] = '';
   }

   public function prepareInputForAdd($input) {
      $input = parent::prepareInputForAdd($input);
      $input['fieldname'] = $this->fieldName;
      if (!isset($input['values'])) {
         $input['values'] = '';
      }
      return $input;
   }

   public function getFieldName() {
      return $this->fieldName;
   }

   public function export(bool $remove_uuid = false): array {
      if ($this->isNewItem()) {
         throw new ExportFailureException(sprintf(__('Cannot export an empty object: %s', 'formcreator'), $this->getTypeName()));
      }

      $parameter = $this->fields;

      $questionFk = PluginFormcreatorQuestion::getForeignKeyField();
      unset($parameter[$questionFk]);

      $idToRemove = 'id';
      if ($remove_uuid) {
         $idToRemove = 'uuid';
      }
      unset($parameter[$idToRemove]);

      return $parameter;
   }

   public static function import(PluginFormcreatorLinker $linker, $input = [], $containerId = 0) {
      global $DB;

      if (!isset($input['uuid']) && !isset($input['id'])) {
         throw new ImportFailureException(sprintf(__('UUID or ID is mandatory for %1$s', 'dynamicfields'), static::getTypeName(1)));
      }

      $questionFk = PluginFormcreatorQuestion::getForeignKeyField();
      $input[$questionFk] = $containerId;

      $question = new PluginFormcreatorQuestion();
      $question->getFromDB($containerId);
      $field = $question->getSubField();

      $item = $field->getEmptyParameters();
      $item = $item[$input['fieldname']];

      $itemId = false;
      $idKey  = 'id';
      if (isset($input['uuid'])) {
         $idKey = 'uuid';
         $itemId = plugin_formcreator_getFromDBByField(
            $item,
            'uuid',
            $input['uuid']
         );
      }

      // CommonDBTM::add() and update() handle escaping automatically

      $originalId = $input[$idKey];
      if ($itemId !== false) {
         $input['id'] = $itemId;
         $item->update($input);
      } else {
         unset($input['id']);
         $itemId = $item->add($input);
      }
      if ($itemId === false) {
         throw new ImportFailureException(sprintf(__('Failed to add or update the %1$s', 'dynamicfields'), static::getTypeName(1)));
      }

      $linker->addObject($originalId, $item);

      return $itemId;
   }

   public static function countItemsToImport($input): int {
      return 1;
   }
}