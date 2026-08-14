<?php
/*
 * -------------------------------------------------------------------------
 *  GLPI Plugin "Campos Dinâmicos Formulário" (dynamicfields)
 *  -------------------------------------------------------------------------
 *  Aplicador do patch no Formcreator
 *
 *  O Formcreator descobre os tipos de questão por glob em
 *  plugins/formcreator/inc/field/ e por um namespace FIXO
 *  (GlpiPlugin\Formcreator\Field\<Tipo>Field) - não existe hook oficial para
 *  registrar um tipo de questão de outro plugin. Este script adiciona o hook
 *  "formcreator_get_question_types" ao arquivo inc/fields.class.php do
 *  Formcreator, permitindo que este plugin declare o tipo "dynamic".
 *
 *  Uso:
 *    php apply.php                        # detecta formcreator em ../formcreator
 *    php apply.php --formcreator=/caminho/formcreator
 *    php apply.php --revert               # remove o patch
 *    php apply.php --status               # apenas verifica o estado
 *
 *  É seguro executar de novo após um upgrade do Formcreator (reaplica o patch).
 *  -------------------------------------------------------------------------
 */

// This script rewrites a file of the Formcreator plugin: it must only ever run
// from the command line, never through the web server (which could be abused
// to modify Formcreator source code remotely).
if (PHP_SAPI !== 'cli') {
   http_response_code(403);
   exit;
}

$args = $argv ?? [];

$formcreatorDir = null;
$mode = 'apply';

for ($i = 1; $i < count($args); $i++) {
   $arg = $args[$i];
   if (strpos($arg, '--formcreator=') === 0) {
      $formcreatorDir = substr($arg, strlen('--formcreator='));
   } else if ($arg === '--revert') {
      $mode = 'revert';
   } else if ($arg === '--status') {
      $mode = 'status';
   } else if ($arg === '--help' || $arg === '-h') {
      echo "Uso: php apply.php [--formcreator=/caminho/formcreator] [--revert] [--status]\n";
      exit(0);
   }
}

if ($formcreatorDir === null) {
   // Detect from GLPI context or from the default relative layout
   if (defined('GLPI_ROOT') && is_dir(GLPI_ROOT . '/plugins/formcreator')) {
      $formcreatorDir = GLPI_ROOT . '/plugins/formcreator';
   } elseif (is_dir(__DIR__ . '/../../../formcreator')) {
      $formcreatorDir = __DIR__ . '/../../../formcreator';
   } elseif (is_dir(__DIR__ . '/../formcreator')) {
      $formcreatorDir = __DIR__ . '/../formcreator';
   } else {
      echo "Formcreator não encontrado. Use --formcreator=/caminho/formcreator\n";
      exit(1);
   }
}

$fieldsFile = $formcreatorDir . '/inc/fields.class.php';
if (!is_file($fieldsFile)) {
   echo "Arquivo não encontrado: $fieldsFile\n";
   exit(1);
}

$content = file_get_contents($fieldsFile);

$edits = buildEdits();

function alreadyPatched(string $content): bool {
   return strpos($content, "public static function getExternalTypes()") !== false
      && strpos($content, "formcreator_get_question_types") !== false;
}

if ($mode === 'status') {
   echo alreadyPatched($content) ? "PATCHED\n" : "NOT_PATCHED\n";
   exit(alreadyPatched($content) ? 0 : 1);
}

if ($mode === 'revert') {
   if (!alreadyPatched($content)) {
      echo "Patch não aplicado - nada a reverter.\n";
      exit(0);
   }
   $reverted = applyEdits($content, true);
   if ($reverted === false) {
      echo "Falha ao reverter o patch (conteúdo inesperado).\n";
      exit(1);
   }
   file_put_contents($fieldsFile, $reverted);
   echo "Patch revertido em $fieldsFile\n";
   exit(0);
}

// Apply mode
if (alreadyPatched($content)) {
   echo "Patch já aplicado em $fieldsFile.\n";
   exit(0);
}

$content = applyEdits($content);
if ($content === false) {
   echo "Falha ao aplicar o patch (conteúdo inesperado). O arquivo não foi alterado.\n";
   exit(1);
}
file_put_contents($fieldsFile, $content);
echo "Patch aplicado em $fieldsFile\n";

/**
 * Build the exact (from, to) string pairs used to apply or revert the patch.
 * The edits are pure string substitutions, so they are fully reversible.
 *
 * @return array[]
 */
function buildEdits() {
   $BEGIN = "// BEGIN dynamicfields: merge external question types registered via hook";
   $END   = "// END dynamicfields";

   // 1) getTypes(): merge external types before "return $tab_field_types;"
   $typesBefore = "      }\n\n      return \$tab_field_types;";
   $typesAfter  = "      }\n\n      $BEGIN\n"
      . "      foreach (PluginFormcreatorFields::getExternalTypes() as \$ext_type => \$ext_data) {\n"
      . "         \$tab_field_types[\$ext_type] = \$ext_data['file'] ?? \$ext_data['classname'];\n"
      . "      }\n"
      . "      $END\n\n"
      . "      return \$tab_field_types;";

   // 2) getClasses(): merge external types before "return $classes;"
   $classesBefore = "      }\n\n      return \$classes;";
   $classesAfter  = "      }\n\n      $BEGIN\n"
      . "      foreach (PluginFormcreatorFields::getExternalTypes() as \$ext_type => \$ext_data) {\n"
      . "         \$classes[\$ext_type] = \$ext_data['classname'];\n"
      . "      }\n"
      . "      $END\n\n"
      . "      return \$classes;";

   // 3) getFieldClassname(): external types take precedence
   $classnameBefore = "   public static function getFieldClassname(\$type) {\n"
      . "      return 'GlpiPlugin\\\\Formcreator\\\\Field\\\\' . ucfirst(\$type) . 'Field';\n"
      . "   }";
   $classnameAfter  = "   public static function getFieldClassname(\$type) {\n"
      . "      \$external = PluginFormcreatorFields::getExternalTypes();\n"
      . "      if (isset(\$external[\$type])) {\n"
      . "         return \$external[\$type]['classname'];\n"
      . "      }\n"
      . "      return 'GlpiPlugin\\\\Formcreator\\\\Field\\\\' . ucfirst(\$type) . 'Field';\n"
      . "   }";

   // 4) New getExternalTypes() method, appended right after fieldTypeExists()
   $methodAfter = "   public static function fieldTypeExists(string \$type): bool {\n"
      . "      \$className = self::getFieldClassname(\$type);\n"
      . "      return is_subclass_of(\$className, PluginFormcreatorAbstractField::class, true);\n"
      . "   }";
   $methodWithMethod = $methodAfter . "\n\n"
      . "   /**\n"
      . "    * Get the question types registered by other plugins through the\n"
      . "    * \"formcreator_get_question_types\" hook (added by the dynamicfields plugin).\n"
      . "    *\n"
      . "    * @return array field_type => ['classname' => string, 'file' => string]\n"
      . "    */\n"
      . "   public static function getExternalTypes() {\n"
      . "      static \$types = null;\n"
      . "      if (\$types === null) {\n"
      . "         \$types = [];\n"
      . "         foreach (Plugin::doHookFunction('formcreator_get_question_types', []) as \$type => \$data) {\n"
      . "            if (!is_string(\$type) || \$type === '') {\n"
      . "               continue;\n"
      . "            }\n"
      . "            if (is_string(\$data) && class_exists(\$data)) {\n"
      . "               \$types[\$type] = ['classname' => \$data];\n"
      . "            } else if (is_array(\$data) && isset(\$data['classname']) && class_exists(\$data['classname'])) {\n"
      . "               \$types[\$type] = \$data;\n"
      . "            }\n"
      . "         }\n"
      . "      }\n"
      . "      return \$types;\n"
      . "   }";

   return [
      ['from' => $typesBefore,     'to' => $typesAfter],
      ['from' => $classesBefore,   'to' => $classesAfter],
      ['from' => $classnameBefore, 'to' => $classnameAfter],
      ['from' => $methodAfter,     'to' => $methodWithMethod],
   ];
}

/**
 * Apply (or revert) the exact edits on the given file content.
 *
 * When $revert is true, the substitutions are reversed (the "to" string is
 * replaced back by the "from" string). Every substitution must match
 * exactly once; otherwise false is returned and nothing is written.
 *
 * @param string $content
 * @param bool   $revert
 * @return string|false
 */
function applyEdits(string $content, bool $revert = false) {
   foreach (buildEdits() as $edit) {
      $from = $revert ? $edit['to']   : $edit['from'];
      $to   = $revert ? $edit['from'] : $edit['to'];
      $count = 0;
      $content = str_replace($from, $to, $content, $count);
      if ($count !== 1) {
         return false;
      }
   }
   return $content;
}