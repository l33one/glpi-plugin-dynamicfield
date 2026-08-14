<?php
/*
 * -------------------------------------------------------------------------
 *  GLPI Plugin "Campos Dinâmicos Formulário" (dynamicfields)
 *  -------------------------------------------------------------------------
 *  Formcreator question type "Campo Dinâmico" (type name: "dynamic").
 *
 *  A read-only field automatically filled with the value of an attribute of
 *  the item selected in a previous question ("GLPI object" or "Dropdown")
 *  of the same form.
 *
 *  Runtime behaviour (see js/dynamicfields.js):
 *   - the field renders an <input type="text" readonly> named
 *     formcreator_field_{id}, carrying data-source-question/data-attribute;
 *   - when the source question changes, an AJAX request
 *     (front/ajax.php?action=get_field_value) resolves the attribute value
 *     of the selected item and the value is injected into the input.
 *
 *  -------------------------------------------------------------------------
 *  LICENSE: GPL-2.0+
 *  -------------------------------------------------------------------------
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Formcreator\Exception\ComparisonException;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginDynamicfieldsField
extends PluginFormcreatorAbstractField
{
   public static function getName(): string {
      return __('Dynamic field', 'dynamicfields');
   }

   public static function canRequire(): bool {
      return true;
   }

   public function isPrerequisites(): bool {
      return Plugin::isPluginActive('formcreator');
   }

   /**
    * The type name of this field. Formcreator derives it from the class
    * name, so it has to be overridden to match the "dynamic" type.
    */
   public function getFieldTypeName(): string {
      return 'dynamic';
   }

   public function isPublicFormCompatible(): bool {
      // The field value is computed client-side through an authenticated
      // AJAX endpoint, so it cannot be used in anonymous/public forms.
      return false;
   }

   public function getEmptyParameters(): array {
      $sourceQuestion = new PluginDynamicfieldsSourceQuestionParameter();
      $sourceQuestion->setField($this, [
         'fieldName' => 'source_question',
         'label'     => __('Source question', 'dynamicfields'),
         'fieldType' => ['dynamic'],
      ]);

      $attribute = new PluginDynamicfieldsAttributeParameter();
      $attribute->setField($this, [
         'fieldName' => 'attribute',
         'label'     => __('Attribute to display', 'dynamicfields'),
         'fieldType' => ['dynamic'],
      ]);

      return [
         'source_question' => $sourceQuestion,
         'attribute'       => $attribute,
      ];
   }

   public function showForm(array $options): void {
      $template = '@dynamicfields/field/dynamicfield.html.twig';
      $parameters = $this->getParameters();
      $this->question->fields['default_values'] = Html::entities_deep($this->question->fields['default_values']);
      $this->deserializeValue($this->question->fields['default_values']);

      TemplateRenderer::getInstance()->display($template, [
         'item'            => $this->question,
         'question_params' => $parameters,
         'params'          => $options,
      ]);
   }

   /**
    * Runtime rendering. Outputs a read-only input whose value is filled by
    * the JS when the user changes the source question.
    */
   public function getRenderedHtml($domain, $canEdit = true): string {
      if (!$canEdit) {
         return (string)$this->value;
      }

      $id        = $this->question->getID();
      $fieldName = 'formcreator_field_' . $id;
      $rand      = mt_rand();
      $domId     = $fieldName . '_' . $rand;

      $parameters = $this->getParameters();
      $sourceQuestionId = (int)($parameters['source_question']->fields['values'] ?? 0);
      $attribute        = (string)($parameters['attribute']->fields['values'] ?? '');

      $html = '';
      $html .= Html::input($fieldName, [
         'type'                => 'text',
         'id'                  => $domId,
         'class'               => 'plugin-dynamicfields-value form-control',
         'readonly'            => 'readonly',
         'value'               => Html::cleanInputText($this->value),
         'data-dynamic-field'  => '1',
         'data-source-question' => (string)$sourceQuestionId,
         'data-attribute'      => $attribute,
         'data-ajax-url'       => PluginDynamicfieldsHelper::getAjaxUrl(),
      ]);
      if ($sourceQuestionId > 0 && $attribute !== '') {
         $html .= Html::scriptBlock("$(function() {
            pluginDynamicfieldsBindField('$fieldName', '$rand');
         });");
      }

      return $html;
   }

   public function serializeValue(PluginFormcreatorFormAnswer $formanswer): string {
      if ($this->value === null || $this->value === '') {
         return '';
      }
      return (string)$this->value;
   }

   public function deserializeValue($value) {
      $this->value = ($value !== null && $value !== '')
         ? (string)$value
         : '';
   }

   public function getValueForDesign(): string {
      return (string)$this->value;
   }

   public function getValueForTargetText($domain, $richText): ?string {
      return (string)$this->value;
   }

   public function getValueForApi() {
      return (string)$this->value;
   }

   public function moveUploads() {
   }

   public function getDocumentsForTarget(): array {
      return [];
   }

   public function hasInput($input): bool {
      return isset($input['formcreator_field_' . $this->question->getID()]);
   }

   public function parseAnswerValues($input, $nonDestructive = false): bool {
      $key = 'formcreator_field_' . $this->question->getID();
      if (!isset($input[$key])) {
         return false;
      }
      if (!is_string($input[$key])) {
         return false;
      }
      $this->value = Toolbox::stripslashes_deep($input[$key]);
      return true;
   }

   public function isValid(): bool {
      if ($this->isRequired() && ($this->value === '' || $this->value === null)) {
         Session::addMessageAfterRedirect(
            __('A required field is empty:', 'formcreator') . ' ' . $this->getLabel(),
            false,
            ERROR
         );
         return false;
      }
      return true;
   }

   public function isValidValue($value): bool {
      return true;
   }

   /**
    * Validate the designer input: the source question must be a previous
    * compatible question and the attribute must exist for its itemtype.
    */
   public function prepareQuestionInputForSave($input) {
      $fieldType = $this->getFieldTypeName();

      // The designer submits the parameters flat
      // (_parameters[fieldtype][paramName] = "value"), but import/export and
      // some callers use an array form (_parameters[fieldtype][paramName] =
      // ['values' => "value"]). Normalize both into ['values' => ...].
      $rawSource = $input['_parameters'][$fieldType]['source_question'] ?? '';
      $sourceQuestionId = is_array($rawSource) ? (int)($rawSource['values'] ?? 0) : (int)$rawSource;

      $rawAttribute = $input['_parameters'][$fieldType]['attribute'] ?? '';
      $attribute = is_array($rawAttribute) ? (string)($rawAttribute['values'] ?? '') : (string)$rawAttribute;

      if ($sourceQuestionId <= 0) {
         Session::addMessageAfterRedirect(
            __('Select a question of type "GLPI object" or "Dropdown" first.', 'dynamicfields'),
            false,
            ERROR
         );
         return [];
      }

      $source = new PluginFormcreatorQuestion();
      if (!$source->getFromDB($sourceQuestionId)) {
         Session::addMessageAfterRedirect(
            sprintf(__('The attribute "%s" is not valid for the selected source question.', 'dynamicfields'), $attribute),
            false,
            ERROR
         );
         return [];
      }
      if (!in_array($source->fields['fieldtype'], PluginDynamicfieldsHelper::SOURCE_FIELDTYPES)) {
         Session::addMessageAfterRedirect(
            __('Select a question of type "GLPI object" or "Dropdown" first.', 'dynamicfields'),
            false,
            ERROR
         );
         return [];
      }

      $itemtype = PluginDynamicfieldsHelper::getItemtypeForQuestion($source);
      $attributes = PluginDynamicfieldsHelper::getAttributesForItemtype($itemtype);
      if ($attribute !== '' && !array_key_exists($attribute, $attributes)) {
         Session::addMessageAfterRedirect(
            sprintf(__('The attribute "%s" is not valid for the selected source question.', 'dynamicfields'), $attribute),
            false,
            ERROR
         );
         return [];
      }

      // Normalize back to the array form used by the parameter add/update chain.
      $input['_parameters'][$fieldType]['source_question'] = ['values' => (string)$sourceQuestionId];
      $input['_parameters'][$fieldType]['attribute']       = ['values' => $attribute];

      $input['default_values'] = '';
      return $input;
   }

   public function equals($value): bool {
      $value = html_entity_decode($value);
      return ((string)$this->value) == $value;
   }

   public function notEquals($value): bool {
      return !$this->equals($value);
   }

   public function greaterThan($value): bool {
      $value = html_entity_decode($value);
      return ((string)$this->value) > $value;
   }

   public function lessThan($value): bool {
      $value = html_entity_decode($value);
      return ((string)$this->value) < $value;
   }

   public function regex($value): bool {
      return (preg_match($value, (string)$this->value)) ? true : false;
   }

   public function getHtmlIcon() {
      return '<i class="fas fa-bolt" aria-hidden="true"></i>';
   }

   public function isEditableField(): bool {
      return true;
   }

   public function isVisibleField(): bool {
      return true;
   }
}