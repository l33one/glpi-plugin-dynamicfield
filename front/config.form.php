<?php
/*
 * -------------------------------------------------------------------------
 *  GLPI Plugin "Campos Dinâmicos Formulário" (dynamicfields)
 *  -------------------------------------------------------------------------
 *  Configuration page of the plugin. As the plugin has no tunable option,
 *  the page shows a visual guide: what the "Campo Dinâmico" question type
 *  does and how GLPI administrators build it in the Formcreator designer.
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

// Page style (modern, soft palette).
// GLPI 11 serves static plugin resources from the plugin `public/` directory and
// maps the URL `/plugins/<key>/<resource>` to `public/<resource>`. GLPI 10 serves
// them directly, so the `/public` prefix must be part of the URL there.
global $CFG_GLPI;
$assetsUrl = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/dynamicfields'
   . (version_compare(GLPI_VERSION, '11.0', '>=') ? '' : '/public');
echo Html::css($assetsUrl . '/css/config_manual.css');

$manual = [
   'title'       => __('Dynamic field usage manual', 'dynamicfields'),
   'plugin_name'    => __('Campos Dinâmicos Formulário', 'dynamicfields'),
   'plugin_version' => PLUGIN_DYNAMICFIELDS_VERSION,
   'images_dir'     => $assetsUrl . '/templates/config/images',
   'description'    => __(
      'Extension for the Formcreator plugin. It adds the "Dynamic field" question type: '
      . 'a read-only field that is filled automatically from an attribute of a previous '
      . 'question (GLPI object or dropdown) of the same form.',
      'dynamicfields'
   ),
   'example'        => __(
      'Example: a ticket form asks for "Computer" (GLPI object question); next to it, the '
      . 'Dynamic field displays the location or serial number of the chosen computer -- '
      . 'without the user typing anything.',
      'dynamicfields'
   ),
   'runtime' => [
      'image'   => 'runtime.png',
      'icon'    => 'fas fa-eye',
      'caption' => __(
         'In the final form, the value is filled in automatically and the field is read-only.',
         'dynamicfields'
      ),
   ],
   'sections' => [
      'designer' => [
         'icon'    => 'fas fa-project-diagram',
         'tone'    => 'indigo',
         'title'   => __('Creating a dynamic field (designer)', 'dynamicfields'),
         'steps'   => [
            __('In Form Creator > Forms, open (or create) a form and go to the designer of an item.', 'dynamicfields'),
            __('Add the item that will be the "source": a question of type "GLPI object" or "Dropdown", configured normally (e.g. a Computer object, with the question "Which computer?").', 'dynamicfields'),
            __('Add another item and choose the question type "Dynamic field".', 'dynamicfields'),
            __('Set its parameters: "Source question" and "Attribute to display" (see below).', 'dynamicfields'),
            __('Save the form.', 'dynamicfields'),
         ],
         'image'   => 'designer.png',
         'caption' => __(
            'Adding a question with the "Dynamic field" type in the Formcreator designer.',
            'dynamicfields'
         ),
      ],
      'source_question' => [
         'icon'    => 'fas fa-mouse-pointer',
         'tone'    => 'cyan',
         'title'   => __('What is "Source question"', 'dynamicfields'),
         'text'    => __(
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
         'image'   => 'source.png',
         'caption' => __(
            'The "Source question" parameter lists the previous compatible questions.',
            'dynamicfields'
         ),
      ],
      'attribute' => [
         'icon'    => 'fas fa-list-ul',
         'tone'    => 'violet',
         'title'   => __('What is "Attribute to display"', 'dynamicfields'),
         'text'    => __(
            'After choosing the source, choose which column of the selected item will be '
            . 'displayed. The options are built dynamically from the columns of the source '
            . 'itemtype (e.g. for Computer: name, serial, otherserial, locations_id, ...). '
            . 'Sensitive and system columns are removed automatically.',
            'dynamicfields'
         ),
         'image'   => 'attribute.png',
         'caption' => __(
            'The "Attribute to display" parameter lists the columns of the source item type.',
            'dynamicfields'
         ),
      ],
      'limitations' => [
         'icon'    => 'fas fa-exclamation-triangle',
         'tone'    => 'amber',
         'title'   => __('Limitations', 'dynamicfields'),
         'steps'   => [
            __('The dynamic field does not work in public (anonymous) forms: filling it requires an authenticated session.', 'dynamicfields'),
            __('The source must be a question located before the dynamic field in the form order.', 'dynamicfields'),
            __('Only "GLPI object" and "Dropdown" questions can be used as source.', 'dynamicfields'),
            __('Requires the Formcreator patch (see the README for installation).', 'dynamicfields'),
         ],
         'image'   => 'order.png',
         'caption' => __(
            'The source question must be above the dynamic field; a source below it is ignored.',
            'dynamicfields'
         ),
      ],
   ],
];

Glpi\Application\View\TemplateRenderer::getInstance()->display(
   '@dynamicfields/config/manual.html.twig',
   $manual
);

Html::footer();