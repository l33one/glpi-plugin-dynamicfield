/**
 * -------------------------------------------------------------------------
 *  GLPI Plugin "Campos Dinâmicos Formulário" (dynamicfields)
 *  -------------------------------------------------------------------------
 *  Client side behaviour of the "Campo Dinâmico" question type.
 *
 *  1. Designer (form builder):
 *     pluginDynamicfields_onSourceQuestionChange() reloads the "Attribute"
 *     parameter dropdown through front/ajax.php?action=get_attributes when
 *     the designer changes the source question.
 *
 *  2. End user (form):
 *     pluginDynamicfieldsBindField() binds a "change" listener on the source
 *     question select. When the user picks an item, an AJAX request
 *     (action=get_field_value) resolves the configured attribute and the
 *     value is injected into the read-only input of the dynamic field.
 *  -------------------------------------------------------------------------
 *  LICENSE: GPL-2.0+
 *  -------------------------------------------------------------------------
 */

(function ($) {
   'use strict';

   /**
    * Designer: source question changed. Refresh the attribute dropdown.
    *
    * @param {HTMLElement} select the source question <select>
    */
   window.pluginDynamicfields_onSourceQuestionChange = function (select) {
      var $select = $(select);
      var $wrapper = $select.closest('[data-question-id]');
      if ($wrapper.length === 0) {
         return;
      }

      var questionId = $wrapper.data('question-id');
      var sourceQuestionId = $select.val();
      var ajaxUrl = $wrapper.data('ajax-url');
      var $container = $('#plugin_dynamicfields_attribute_parameter_' + questionId);
      if (!$container.length) {
         return;
      }

      if (!sourceQuestionId) {
         // Keep the parameter present in the submitted form
         // (_parameters[fieldtype][param] is mandatory for Formcreator).
         $container.empty();
         $container.append($('<input>', {
            type: 'hidden',
            name: $container.data('param-name'),
            value: ''
         }));
         return;
      }

      $.ajax({
         url: ajaxUrl,
         type: 'GET',
         data: {
            action: 'get_attributes',
            source_question_id: sourceQuestionId,
            question_id: questionId
         },
         dataType: 'html',
         success: function (html) {
            $container.replaceWith(html);
         },
         error: function () {
            // Keep the parameter present so the question can still be saved.
            $container.empty();
            $container.append($('<input>', {
               type: 'hidden',
               name: $container.data('param-name'),
               value: ''
            }));
         }
      });
   };

   /**
    * Designer: initialise the "Attribute" parameter when a source question is
    * already selected on page load. This happens when the form has a single
    * compatible question (the <select> is pre-select its only option), so the
    * "change" event never fires and the attribute dropdown would stay empty.
    *
    * @param {number} questionId id of the dynamic field question
    */
   window.pluginDynamicfieldsInitSourceQuestion = function (questionId) {
      var $wrapper = $('#plugin_dynamicfields_source_question_' + questionId);
      var $select = $wrapper.length ? $wrapper.find('select').first() : $();

      // The "attribute" parameter is rendered after the "source question" one;
      // retry briefly in case the container is not in the DOM yet.
      var $container = $('#plugin_dynamicfields_attribute_parameter_' + questionId);
      if ($wrapper.length && $select.length && !$container.length) {
         window.setTimeout(function () {
            window.pluginDynamicfieldsInitSourceQuestion(questionId);
         }, 100);
         return;
      }

      if (!$wrapper.length || !$select.length || !$select.val() || !$container.length) {
         return;
      }

      // Skip when the attribute dropdown is already rendered (e.g. an edited
      // question whose parameters were persisted server-side).
      if ($container.find('select').length > 0) {
         return;
      }

      window.pluginDynamicfields_onSourceQuestionChange($select[0]);
   };

   /**
    * End user: bind a dynamic field input to its source question.
    *
    * @param {string} fieldName name of the dynamic field input
    * @param {string} rand      random suffix of the input DOM id
    */
   window.pluginDynamicfieldsBindField = function (fieldName, rand) {
      var $input = $('#' + fieldName + '_' + rand);
      if ($input.length === 0) {
         return;
      }

      var sourceQuestionId = $input.data('source-question');
      var attribute = $input.data('attribute');
      var ajaxUrl = $input.data('ajax-url');
      if (!sourceQuestionId || !attribute || !ajaxUrl) {
         return;
      }

      var sourceFieldName = 'formcreator_field_' + sourceQuestionId;

      var refresh = function () {
         var $source = $('select[name="' + sourceFieldName + '"]');
         var itemsId = $source.length ? $source.val() : null;
         if (!itemsId) {
            $input.val('');
            return;
         }

         $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: {
               action: 'get_field_value',
               source_question_id: sourceQuestionId,
               items_id: itemsId,
               attribute: attribute
            },
            dataType: 'json',
            success: function (response) {
               $input.val((response && response.value) ? response.value : '');
            },
            error: function () {
               $input.val('');
            }
         });
      };

      // Refresh when the user changes the source question
      $(document).on('change', 'select[name="' + sourceFieldName + '"]', refresh);

      // Refresh when the form (re)evaluates question visibility
      $(document).on('plugin_formcreator.reform plugin_dynamicfields.refresh', refresh);

      // Initial value (e.g. when a default answer is pre-filled)
      refresh();
   };
})(window.jQuery || {});