<?php
/*
 * -------------------------------------------------------------------------
 *  GLPI Plugin "Campos Dinâmicos Formulário" (dynamicfields)
 *  -------------------------------------------------------------------------
 *  Configuration page of the plugin. As the plugin has no tunable option,
 *  the page shows a short user manual: how to build a "Campo Dinâmico"
 *  question in the Formcreator designer and how it behaves at runtime.
 *
 *  Reached from the Plugins list via the "Configure" button
 *  ($PLUGIN_HOOKS['config_page']).
 *  -------------------------------------------------------------------------
 *  LICENSE: GPL-2.0+
 *  -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

// Read-only documentation page: any user allowed to view the plugin
// configuration can open it.
Session::checkRight('config', READ);

Html::header(
   __('Dynamic field usage manual', 'dynamicfields'),
   $_SERVER['PHP_SELF'],
   'config',
   'plugin',
   'dynamicfields'
);

$manual = [
   'title' => __('Dynamic field usage manual', 'dynamicfields'),
   'plugin_name'    => __('Campos Dinâmicos Formulário', 'dynamicfields'),
   'plugin_version' => PLUGIN_DYNAMICFIELDS_VERSION,
   'intro'          => __(
      'Adds the question type "Dynamic field" to Formcreator: a read-only field that is '
      . 'automatically filled from an attribute of a previous question (GLPI object or '
      . 'dropdown) of the same form.',
      'dynamicfields'
   ),
   'example'        => __(
      'Example: a ticket form asks for "Computer" (GLPI object question); next to it, the '
      . 'Dynamic field displays the location or serial number of the chosen computer -- '
      . 'without the user typing anything.',
      'dynamicfields'
   ),
   'sections' => [
      'designer' => [
         'title'  => __('Creating a dynamic field (designer)', 'dynamicfields'),
         'steps'  => [
            __('In Form Creator > Forms, open (or create) a form and go to the designer of an item.', 'dynamicfields'),
            __('Add the item that will be the "source": a question of type "GLPI object" or "Dropdown", configured normally (e.g. a Computer object, with the question "Which computer?").', 'dynamicfields'),
            __('Add another item and choose the question type "Dynamic field".', 'dynamicfields'),
            __('Set its parameters: "Source question" and "Attribute to display" (see below).', 'dynamicfields'),
            __('Save the form.', 'dynamicfields'),
         ],
      ],
      'source_question' => [
         'title' => __('What is "Source question"', 'dynamicfields'),
         'text'  => __(
            'It is the previous question of the form that "provides" the object. The list shows '
            . 'only compatible questions (GLPI object or Dropdown) located before the dynamic '
            . 'field, in the format:',
            'dynamicfields'
         ),
         'example' => '[Computer] Which computer?',
         'note'    => __(
            'When there is only one compatible source, it is already preselected automatically.',
            'dynamicfields'
         ),
      ],
      'attribute' => [
         'title' => __('What is "Attribute to display"', 'dynamicfields'),
         'text'  => __(
            'After choosing the source, choose which column of the selected item will be '
            . 'displayed. The options are built dynamically from the columns of the source '
            . 'itemtype (e.g. for Computer: name, serial, otherserial, locations_id, ...). '
            . 'Sensitive and system columns are removed automatically.',
            'dynamicfields'
         ),
      ],
      'runtime' => [
         'title' => __('Behaviour in the running form (end user)', 'dynamicfields'),
         'steps' => [
            __('When the user fills the source question (selects the computer):', 'dynamicfields'),
            __('the dynamic field is filled automatically with the value of the chosen attribute;', 'dynamicfields'),
            __('the field is read-only (it cannot be edited);', 'dynamicfields'),
            __('the value is kept if the user changes the object in the source (it is recalculated);', 'dynamicfields'),
            __('on submit, the value is saved in the form answer and appears in ##FULLFORM## in the generated ticket.', 'dynamicfields'),
         ],
      ],
      'limitations' => [
         'title' => __('Limitations', 'dynamicfields'),
         'steps' => [
            __('The dynamic field does not work in public (anonymous) forms: filling it requires an authenticated session.', 'dynamicfields'),
            __('The source must be a question located before the dynamic field in the form order.', 'dynamicfields'),
            __('Only "GLPI object" and "Dropdown" questions can be used as source.', 'dynamicfields'),
            __('Requires the Formcreator patch (see the README for installation).', 'dynamicfields'),
         ],
      ],
   ],
];

Glpi\Application\View\TemplateRenderer::getInstance()->display(
   '@dynamicfields/config/manual.html.twig',
   $manual
);

Html::footer();