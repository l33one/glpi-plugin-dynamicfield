<?php
/*
 * -------------------------------------------------------------------------
 *  GLPI Plugin "Campos Dinâmicos Formulário" (dynamicfields)
 *  -------------------------------------------------------------------------
 *  Question parameter "source_question".
 *
 *  Lets the form designer pick which previous question ("GLPI object" or
 *  "Dropdown") of the form provides the item whose attribute will be
 *  displayed by the dynamic field.
 *
 *  Stored in glpi_plugin_dynamicfields_sourcequestionparameters.
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

class PluginDynamicfieldsSourceQuestionParameter
extends PluginFormcreatorAbstractQuestionParameter
{
   public static function getTypeName($nb = 0) {
      return _n('Dynamic field source question', 'Dynamic field source questions', $nb, 'dynamicfields');
   }

   public function rawSearchOptions() {
      $tab = parent::rawSearchOptions();

      $tab[] = [
         'id'                 => '4',
         'table'              => $this::getTable(),
         'field'              => 'values',
         'name'               => __('Source question', 'dynamicfields'),
         'datatype'           => 'text',
         'massiveaction'      => false,
      ];

      return $tab;
   }

   public function getParameterFormSize() {
      return 1;
   }

   public function getParameterForm(PluginFormcreatorQuestion $question) {
      $name = '_parameters[' . $this->field->getFieldTypeName() . '][' . $this->fieldName . ']';

      $savedValue = '';
      $this->getFromDBByCrit([
         'plugin_formcreator_questions_id' => $question->getID(),
         'fieldname'                        => $this->fieldName,
      ]);
      if (!$this->isNewItem()) {
         $savedValue = (string)$this->fields['values'];
      }

      $out = TemplateRenderer::getInstance()->render(
         '@dynamicfields/questionparameter/dynamic_source_question.html.twig',
         [
            'name'        => $name,
            'value'       => $savedValue,
            'options'     => PluginDynamicfieldsHelper::getCompatibleSourceQuestions($question->getID(), $question),
            'label'       => __('Source question', 'dynamicfields'),
            'question_id' => $question->getID(),
            'ajax_url'    => PluginDynamicfieldsHelper::getAjaxUrl(),
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

      if (isset($input['values'])) {
         $input['values'] = $DB->escape($input['values']);
      }

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