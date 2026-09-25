<?php
/*
 * -------------------------------------------------------------------------
 *  GLPI Plugin "Campos Dinâmicos Formulário" (dynamicfields)
 *  -------------------------------------------------------------------------
 *  Hooks de instalação e o hook de registro de tipos de questão do
 *  Formcreator.
 *  -------------------------------------------------------------------------
 *  LICENSE: GPL-2.0+
 *  -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Install hook
 *
 * Creates the tables used to persist the two question parameters of the
 * "Campo Dinâmico" type (source question + attribute). The tables are owned
 * by this plugin, but follow the Formcreator question parameter convention
 * (plugin_formcreator_questions_id + fieldname).
 *
 * @return boolean
 */
function plugin_dynamicfields_install() {
   global $DB;

   $migration = new Migration(PLUGIN_DYNAMICFIELDS_VERSION);

   // GLPI 11 deprecates DBmysql::query() (throws "Executing direct queries is
   // not allowed!"); GLPI 10.0.11+ exposes doQuery(). Keep a fallback for old
   // 10.0.x where doQuery() does not exist yet.
   $runQuery = static function (string $sql) use ($DB): void {
      $result = method_exists($DB, 'doQuery') ? $DB->doQuery($sql) : $DB->query($sql);
      if ($result === false || $result === null) {
         die($DB->error());
      }
   };

   $tables = [
      'glpi_plugin_dynamicfields_sourcequestionparameters',
      'glpi_plugin_dynamicfields_attributeparameters',
   ];

   foreach ($tables as $table) {
      if (!$DB->tableExists($table)) {
         $query = "CREATE TABLE `$table` (
            `id`                              int UNSIGNED NOT NULL AUTO_INCREMENT,
            `plugin_formcreator_questions_id` int UNSIGNED NOT NULL DEFAULT '0',
            `fieldname`                       varchar(255) NOT NULL DEFAULT '',
            `values`                          longtext,
            `uuid`                            varchar(80) DEFAULT '',
            PRIMARY KEY (`id`)
         ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;";
         $runQuery($query);
      }
      $migration->addKey($table, 'plugin_formcreator_questions_id');
      $migration->addKey($table, ['plugin_formcreator_questions_id', 'fieldname']);
   }

   $migration->executeMigration();

   return true;
}

/**
 * Uninstall hook
 *
 * Removes the tables created by this plugin. Rows of the Formcreator
 * question parameters owned by other plugins are left untouched.
 *
 * @return boolean
 */
function plugin_dynamicfields_uninstall() {
   global $DB;

   $runQuery = static function (string $sql) use ($DB): void {
      $result = method_exists($DB, 'doQuery') ? $DB->doQuery($sql) : $DB->query($sql);
      if ($result === false || $result === null) {
         die($DB->error());
      }
   };

   foreach ([
      'glpi_plugin_dynamicfields_sourcequestionparameters',
      'glpi_plugin_dynamicfields_attributeparameters',
   ] as $table) {
      if ($DB->tableExists($table)) {
         $runQuery("DROP TABLE `$table`;");
      }
   }

   return true;
}

/**
 * Hook "formcreator_get_question_types" (exposed by the Formcreator patch).
 *
 * Declares the new question type to Formcreator.
 *
 * @return array field_type => ['classname' => string, 'file' => string]
 */
function plugin_dynamicfields_get_question_types() {
   return [
      'dynamic' => [
         'classname' => 'PluginDynamicfieldsField',
         'file'      => PLUGIN_DYNAMICFIELDS_ROOT . '/inc/field.class.php',
      ],
   ];
}