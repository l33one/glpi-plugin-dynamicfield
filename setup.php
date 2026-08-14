<?php
/*
 * -------------------------------------------------------------------------
 *  GLPI Plugin "Campos Dinâmicos Formulário" (dynamicfields)
 *  Copyright (C) 2026 by Leewan Meneses.
 *
 *  Extensão para o plugin Formcreator: adiciona o tipo de questão
 *  "Campo Dinâmico", um campo somente-leitura preenchido automaticamente a
 *  partir de um atributo de uma questão anterior (objeto GLPI ou lista
 *  suspensa) do mesmo formulário.
 *
 *  FLUXO DE DADOS:
 *   1. Designer (backend): a questão "Campo Dinâmico" guarda 2 parâmetros,
 *      "Questão de Origem" e "Atributo a Exibir". Ao escolher a origem, um
 *      AJAX (js/dynamicfields.js -> front/ajax.php?action=get_attributes)
 *      consulta as colunas da tabela do itemtype (ex.: Computer) e popula o
 *      select de atributos. Os parâmetros são persistidos pelo Formcreator
 *      na tabela glpi_plugin_formcreator_questionparameters.
 *   2. Usuário (frontend): o campo é um <input type="text" readonly>. Um
 *      event listener (change) na questão de origem dispara um AJAX
 *      (action=get_field_value) que devolve o valor do atributo do item
 *      selecionado; o valor é injetado no input.
 *   3. Submit: o valor viaja em `formcreator_field_{id}` e é gravado nas
 *      respostas do Formcreator, aparecendo no ticket (##FULLFORM##).
 *
 *  --------------------------------------------------------------------------
 *  LICENSE: GPL-2.0+
 *  --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

define('PLUGIN_DYNAMICFIELDS_VERSION', '1.0.0');
define('PLUGIN_DYNAMICFIELDS_ROOT', __DIR__);

/**
 * Init the hooks of the plugin
 *
 * @return void
 */
function plugin_init_dynamicfields() {
   global $PLUGIN_HOOKS;

   // Always register CSRF compliance so the plugin hooks can be handled
   $PLUGIN_HOOKS['csrf_compliant']['dynamicfields'] = true;

   if (Plugin::isPluginActive('dynamicfields')
       && Plugin::isPluginActive('formcreator')) {
      // Register the new question type "Campo Dinâmico" into Formcreator.
      // This relies on the patch applied to formcreator/inc/fields.class.php
      // (see patches/apply.php) that exposes the "formcreator_get_question_types" hook.
      $PLUGIN_HOOKS['formcreator_get_question_types']['dynamicfields']
         = 'plugin_dynamicfields_get_question_types';

      // Assets used by the designer (front/pages.form.php) and by the end
      // user form (front/form.php).
      $PLUGIN_HOOKS['add_css']['dynamicfields']        = 'css/dynamicfields.css';
      $PLUGIN_HOOKS['add_javascript']['dynamicfields'] = 'js/dynamicfields.js';
   }
}

/**
 * Get the name and details of the plugin
 *
 * @return array
 */
function plugin_version_dynamicfields() {
   return [
      'name'         => __('Campos Dinâmicos Formulário', 'dynamicfields'),
      'version'      => PLUGIN_DYNAMICFIELDS_VERSION,
      'author'       => 'Leewan Meneses',
      'license'      => 'GPLv2+',
      'homepage'     => '',
      'requirements' => [
         'glpi' => [
            'min' => '10.0.0',
         ],
         'php' => [
            'min' => '8.0.0',
         ],
         'plugins' => [
            'formcreator' => [
               'min' => '2.13.0',
            ],
         ],
      ],
   ];
}

/**
 * Check prerequisites for installation
 *
 * @return boolean
 */
function plugin_check_prerequisites_dynamicfields() {
   if (!is_dir(GLPI_ROOT . '/plugins/dynamicfields')) {
      echo __('The plugin directory must be named "dynamicfields", so install the plugin under plugins/dynamicfields.', 'dynamicfields');
      return false;
   }

   if (!class_exists('PluginFormcreatorAbstractField')) {
      echo __('The Formcreator plugin must be installed and active (version 2.13 or above).', 'dynamicfields');
      return false;
   }

   if (!method_exists('PluginFormcreatorFields', 'getExternalTypes')) {
      echo __('Formcreator is not patched to accept external question types. Apply the patch with: php plugins/dynamicfields/patches/apply.php', 'dynamicfields');
      return false;
   }

   return true;
}

/**
 * Check configuration
 *
 * @param boolean $verbose Verbose mode
 * @return boolean
 */
function plugin_check_config_dynamicfields($verbose = false) {
   // Verify the new question type is registered in the Formcreator registry
   if (Plugin::isPluginActive('dynamicfields')) {
      $types = PluginFormcreatorFields::getTypes();
      if (!isset($types['dynamic'])) {
         if ($verbose) {
            echo __('The question type "Campo Dinâmico" is not registered.', 'dynamicfields');
         }
         return false;
      }
   }
   return true;
}
