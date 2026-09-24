<?php
/*
 * -------------------------------------------------------------------------
 *  GLPI Plugin "Campos Dinâmicos Formulário" (dynamicfields)
 *  -------------------------------------------------------------------------
 *  AJAX endpoints of the plugin.
 *
 *  Actions:
 *   - get_attributes  (designer)   -> HTML snippet for the "Attribute"
 *     parameter dropdown, based on the selected source question.
 *   - get_field_value (end user)   -> JSON {value, display} with the value
 *     of the chosen attribute for the item selected in the source question.
 *
 *  Both actions require an authenticated session. CSRF protection:
 *   - GLPI 11 ships Html::checkAjaxInput();
 *   - GLPI 10 has no equivalent, so we validate the token manually (header
 *     X-Glpi-Csrf-Token or _glpi_csrf_token request param) whenever present.
 *  -------------------------------------------------------------------------
 *  LICENSE: GPL-2.0+
 *  -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

// CSRF check: use the GLPI 11 helper when available, otherwise follow the
// GLPI 10 behaviour (GLPI 10 has no checkAjaxInput() and does not attach CSRF
// tokens to AJAX GET requests — its own ajax endpoints only call
// checkLoginUser()). When a token is supplied, validate it against the
// session token map without consuming it, so that repeated calls from the
// same rendered page keep working.
if (method_exists('Html', 'checkAjaxInput')) {
   Html::checkAjaxInput();
} else {
   $provided_token = $_SERVER['HTTP_X_GLPI_CSRF_TOKEN'] ?? ($_REQUEST['_glpi_csrf_token'] ?? null);
   if ($provided_token !== null) {
      $token_expires = $_SESSION['glpicsrftokens'][$provided_token] ?? 0;
      if (!is_int($token_expires) || $token_expires < time()) {
         http_response_code(403);
         exit;
      }
   }
}

$action = $_REQUEST['action'] ?? '';
if ($action === '') {
   http_response_code(400);
   exit;
}

switch ($action) {
   case 'get_attributes':
      getAttributes();
      break;

   case 'get_field_value':
      getFieldValue();
      break;

   default:
      http_response_code(400);
      exit;
}

/**
 * Designer endpoint: re-renders the attribute parameter dropdown when the
 * designer changes the source question.
 */
function getAttributes() {
   Session::checkRight('entity', UPDATE);

   $sourceQuestionId = (int)($_REQUEST['source_question_id'] ?? 0);
   $questionId       = (int)($_REQUEST['question_id'] ?? 0);
   if ($sourceQuestionId <= 0) {
      http_response_code(400);
      exit;
   }

   $source = new PluginFormcreatorQuestion();
   if (!$source->getFromDB($sourceQuestionId)) {
      http_response_code(404);
      exit;
   }
   if (!in_array($source->fields['fieldtype'], PluginDynamicfieldsHelper::SOURCE_FIELDTYPES)) {
      http_response_code(400);
      exit;
   }

   // The designer may be editing an existing question whose type was just
   // changed to "dynamic" but not saved yet: the question loaded from the DB
   // still has its OLD fieldtype. The parameters must be rendered under the
   // CURRENT designer fieldtype (which is always "dynamic" for this plugin),
   // otherwise the name would be _parameters[oldtype][attribute] and the save
   // would be rejected by Formcreator's checkBeforeSave().
   if ($questionId > 0) {
      $question = new PluginFormcreatorQuestion();
      if (!$question->getFromDB($questionId)) {
         http_response_code(404);
         exit;
      }
      $question->fields['fieldtype'] = $_REQUEST['fieldtype'] ?? 'dynamic';
   } else {
      $question = new PluginFormcreatorQuestion();
      $question->getEmpty();
      $question->fields['fieldtype'] = $_REQUEST['fieldtype'] ?? 'dynamic';
   }

   // Keep the currently saved attribute as the selected value
   $savedValue = '';
   if ($questionId > 0) {
      $attribute = new PluginDynamicfieldsAttributeParameter();
      $attribute->getFromDBByCrit([
         'plugin_formcreator_questions_id' => $questionId,
         'fieldname'                        => 'attribute',
      ]);
      if (!$attribute->isNewItem()) {
         $savedValue = (string)$attribute->fields['values'];
      }
   }

   echo PluginDynamicfieldsAttributeParameter::renderForSource($question, $sourceQuestionId, $savedValue);
   exit;
}

/**
 * End-user endpoint: computes the attribute value of the item selected in
 * the source question.
 */
function getFieldValue() {
   $sourceQuestionId = (int)($_REQUEST['source_question_id'] ?? 0);
   $itemsId          = $_REQUEST['items_id'] ?? '';
   $attribute        = (string)($_REQUEST['attribute'] ?? '');

   if ($sourceQuestionId <= 0) {
      http_response_code(400);
      exit;
   }

   $result = ['value' => '', 'display' => ''];

   $source = new PluginFormcreatorQuestion();
   if ($source->getFromDB($sourceQuestionId)) {
      if (in_array($source->fields['fieldtype'], PluginDynamicfieldsHelper::SOURCE_FIELDTYPES)) {
         $itemtype = PluginDynamicfieldsHelper::getItemtypeForQuestion($source);
         $attributes = PluginDynamicfieldsHelper::getAttributesForItemtype($itemtype);
         if ($attribute !== '' && (array_key_exists($attribute, $attributes) || in_array($attribute, ['name', 'completename']))) {
            $result = PluginDynamicfieldsHelper::resolveAttributeValue($itemtype, $itemsId, $attribute);
         }
      }
   }

   header('Content-Type: application/json; charset=UTF-8');
   echo json_encode($result, JSON_UNESCAPED_UNICODE);
   exit;
}