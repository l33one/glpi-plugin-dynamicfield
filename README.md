# Campos Dinâmicos Formulário (dynamicfields)

Extensão para o plugin **GLPI Formcreator** que adiciona o tipo de questão **"Campo Dinâmico"** (`dynamic`): um campo **somente-leitura** preenchido automaticamente a partir de um **atributo de uma questão anterior** do mesmo formulário.

> **Exemplo de uso:** um formulário de abertura de chamado pede "Computador" (questão *Objeto do GLPI*). Ao lado, o *Campo Dinâmico* exibe automaticamente a **localização** ou o **número de série** do computador escolhido — sem o usuário precisar digitar nada.

| | |
|---|---|
| **GLPI** | 10.0+ (testado em 10.0.x) — compatibilidade básica com 11.0 |
| **Formcreator** | 2.13.x |
| **PHP** | 8.0+ |
| **Licença** | GPL-2.0-or-later |

---

## Índice

1. [Funcionalidades](#funcionalidades)
2. [Limitações](#limitações)
3. [Instalação](#instalação)
   1. [Requisitos](#requisitos)
   2. [Patch obrigatório no Formcreator](#patch-obrigatório-no-formcreator)
   3. [Ativação dos plugins](#ativação-dos-plugins)
   4. [Resolução de problemas na instalação](#resolução-de-problemas-na-instalação)
4. [Manual do usuário (designer)](#manual-do-usuário-designer)
   1. [Criando um campo dinâmico](#criando-um-campo-dinâmico)
   2. [O que é "Questão de origem"](#o-que-é-questão-de-origem)
   3. [O que é "Atributo a exibir"](#o-que-é-atributo-a-exibir)
   4. [Comportamento no formulário em execução](#comportamento-no-formulário-em-execução)
5. [Documentação técnica](#documentação-técnica)
   1. [Arquitetura e fluxo de dados](#arquitetura-e-fluxo-de-dados)
   2. [Estrutura de arquivos](#estrutura-de-arquivos)
   3. [Tabelas do banco de dados](#tabelas-do-banco-de-dados)
   4. [Endpoints AJAX](#endpoints-ajax)
   5. [Registro do tipo de questão (patch)](#registro-do-tipo-de-questão-patch)
   6. [Atributos exibidos](#atributos-exibidos)
6. [FAQ / Solução de problemas](#faq--solução-de-problemas)
7. [Desenvolvimento e contribuição](#desenvolvimento-e-contribuição)
8. [Licença](#licença)

---

## Funcionalidades

* Novo tipo de questão **"Campo Dinâmico"** no designer de formulários do Formcreator.
* **Origem apenas compatível**: questões anteriores do tipo **Objeto do GLPI** (`glpiselect`) ou **Dropdown** (`dropdown`).
* **Atributo a exibir**: colunas da tabela principal do itemtype da origem (ex.: `name`, `serial`, `otherserial`, `locations_id`, ...).
* **Colunas sensíveis bloqueadas**: senhas, tokens, PIN e demais credenciais nunca aparecem na lista de atributos.
* **Colunas de sistema bloqueadas**: `id`, `entities_id`, `is_deleted`, `is_template`, etc., também são ocultadas.
* **Valor em tempo real**: ao selecionar o objeto/serial na origem, o campo dinâmico é preenchido via AJAX, sem recarregar a página.
* **Persistência nativa**: o valor viaja no submit como `formcreator_field_{id}` e fica gravado na resposta do formulário (aparece no ticket via `##FULLFORM##`).
* **Atributos calculados**: valores que não são colunas puras do item são resolvidos pelo motor de busca do GLPI (tabelas de terceiros).
* **Compatível com "obrigatório"**: o campo dinâmico pode ser marcado como obrigatório no designer.
* **i18n**: português do Brasil e inglês.

---

## Limitações

* O campo dinâmico **não funciona em formulários públicos** (anônimos): o preenchimento via AJAX exige sessão autenticada. Se você precisa deste caso, colabore via issue/pull request.
* A origem deve ser uma questão **anterior** ao campo dinâmico na ordem do formulário.
* Apenas **Objeto do GLPI** e **Dropdown** podem ser origens (não é possível, hoje, usar "Texto", "Select", etc. como origem).
* Exige o **patch no Formcreator** (ver [Instalação](#instalação)) — sem ele o tipo de questão não aparece.

---

## Instalação

### Requisitos

* GLPI **10.0.x** instalado e funcional.
* Plugin **Formcreator 2.13.x** instalado e **ativo**.
* PHP **8.0+**.

### Patch obrigatório no Formcreator

O Formcreator descobre tipos de questão por `glob` de arquivos em `inc/field/` e por um **namespace fixo** (`GlpiPlugin\Formcreator\Field\<Tipo>Field`) — **não existe** hook oficial para registrar um tipo de outro plugin.

Este plugin adiciona esse hook (`formcreator_get_question_types`) no Formcreator por meio de um script **idempotente** e **reversível**:

```bash
cd glpi
php plugins/dynamicfields/patches/apply.php
```

O script detecta automaticamente o Formcreator em `plugins/formcreator`. Opções:

```bash
php plugins/dynamicfields/patches/apply.php --status                     # verifica o estado (PATCHED / NOT_PATCHED)
php plugins/dynamicfields/patches/apply.php --formcreator=/caminho/formcreator
php plugins/dynamicfields/patches/apply.php --revert                     # remove o patch (reversível)
```

> Após **atualizar o Formcreator**, reexecute o `apply.php` para reaplicar o patch (a atualização sobrescreve o arquivo `inc/fields.class.php`).

### Ativação dos plugins

1. Em **Administração → Plugins**, ative primeiro o **Formcreator**.
2. Ative depois **Campos Dinâmicos Formulário**.

> O formulário só oferece o tipo "Campo Dinâmico" se o patch tiver sido aplicado **e** o Formcreator estiver ativo.

### Resolução de problemas na instalação

| Sintoma | Causa provável | Solução |
|---|---|---|
| O plugin requisita "Formcreator is not patched..." | Patch não aplicado | `php plugins/dynamicfields/patches/apply.php` |
| O tipo "Campo Dinâmico" não aparece no designer | Patch não aplicado ou Formcreator inativo | Reaplique o patch; confirme o Formcreator ativo |
| O diretório do plugin deve chamar `dynamicfields` | Nome da pasta incorreto | Instale em `plugins/dynamicfields` |
| Erro após upgrade do Formcreator | O patch foi sobrescrito | Reexecute `apply.php` |
| Erro na pré-verificação de config | Tipo não registrado | Aplique o patch e ative/desative o plugin |

---

## Manual do usuário (designer)

### Criando um campo dinâmico

1. No **Form Creator → Forms**, abra (ou crie) um formulário e vá ao **designer** de um item.
2. Adicione o **item** que será a "origem" — uma questão do tipo **Objeto do GLPI** ou **Dropdown** — configurada normalmente (ex.: um objeto `Computer`, com a pergunta "Qual computador?").
3. Adicione outro **item** e escolha o tipo de questão **Campo Dinâmico**.
4. Configure os parâmetros do campo dinâmico (ver abaixo).
5. **Salve** o formulário.

### O que é "Questão de origem"

É a questão anterior do formulário que vai "fornecer" o objeto. A lista mostra apenas questões compatíveis (Objeto do GLPI ou Dropdown) **posicionadas antes** do campo dinâmico, com o formato:

```
[Computer] Qual computador?
```

Quando só existe uma origem compatível, ela já é pré-selecionada automaticamente.

### O que é "Atributo a exibir"

Após escolher a origem, escolha **qual coluna** do item selecionado será exibida. As opções são montadas dinamicamente das colunas do itemtype da origem (ex.: para `Computer`: `name`, `serial`, `otherserial`, `locations_id`, ...). Colunas sensíveis e de sistema são removidas automaticamente.

### Comportamento no formulário em execução

Quando o usuário preenche a **questão de origem** (seleciona o computador):

1. O campo dinâmico é **preenchido automaticamente** com o valor do atributo escolhido.
2. O campo fica **somente-leitura** (não é possível editar).
3. O valor permanece caso o usuário troque o objeto na origem (é recalculado).
4. No **submit**, o valor é salvo na resposta do formulário e aparece em `##FULLFORM##` no ticket gerado.

> O campo dinâmico pode ser marcado como **obrigatório** — a validacão é feita no momento da submissão.

---

## Documentação técnica

### Arquitetura e fluxo de dados

```
┌────────────────────────── DESIGNER ──────────────────────────┐
│ 1. Designer escolhe "Questão de origem" no parâmetro         │
│ 2. JS (js/dynamicfields.js) dispara                          │
│    front/ajax.php?action=get_attributes                      │
│    -> popula o <select> "Atributo a exibir" com as colunas   │
│       do itemtype da origem (helper::getAttributesForItemtype)│
│ 3. Ao salvar, Formcreator persiste os 2 parâmetros nas       │
│    tabelas deste plugin (via prepareQuestionInputForSave)    │
└──────────────────────────────────────────────────────────────┘

┌────────────────────────── FRONTEND ──────────────────────────┐
│ 1. O campo renderiza <input type="text" readonly> nomeado    │
│    formcreator_field_{id}, com data-source-question/attribute│
│ 2. Ao mudar a questão de origem, o JS dispara                │
│    front/ajax.php?action=get_field_value                     │
│    -> helper::resolveAttributeValue() resolve o valor        │
│    -> valor injetado no input                                │
│ 3. No submit, o valor viaja em formcreator_field_{id}        │
│    e é gravado na resposta do Formcreator (##FULLFORM##)     │
└──────────────────────────────────────────────────────────────┘
```

Componentes principais:

* `PluginDynamicfieldsField` (`inc/field.class.php`): o tipo de questão. Registra os dois parâmetros, renderiza o campo (designer + runtime), faz parsing/validação do valor e valida o designer (`prepareQuestionInputForSave`).
* `PluginDynamicfieldsHelper` (`inc/helper.class.php`): utilitários — lista origens compatíveis, lista atributos exibíveis de um itemtype, resolve o valor de um atributo para um item (coluna direta ou via motor de busca).
* `PluginDynamicfieldsSourceQuestionParameter` e `PluginDynamicfieldsAttributeParameter`: os dois parâmetros de designer, seguindo a convenção do Formcreator (`plugin_formcreator_questions_id` + `fieldname` + `values` + `uuid`).
* `front/ajax.php`: os dois endpoints `get_attributes` (designer) e `get_field_value` (usuário final).
* `patches/apply.php`: injeta o hook `formcreator_get_question_types` no Formcreator.

### Estrutura de arquivos

```
dynamicfields/
├── setup.php                        # declaração, requisitos e hooks do plugin
├── hook.php                         # install/uninstall (tabelas) + registro do tipo "dynamic"
├── dynamicfields.xml                # metadados para o marketplace do GLPI
├── README.md                        # este documento
├── locales/
│   ├── pt_BR.po / pt_BR.mo          # português do Brasil
│   └── en_GB.po / en_GB.mo          # inglês (padrão)
├── inc/
│   ├── field.class.php              # PluginDynamicfieldsField (tipo de questão)
│   ├── helper.class.php             # PluginDynamicfieldsHelper (fontes, atributos, resolução)
│   ├── sourcequestionparameter.class.php
│   └── attributeparameter.class.php
├── templates/
│   ├── field/dynamicfield.html.twig
│   └── questionparameter/
│       ├── dynamic_source_question.html.twig
│       └── dynamic_attribute.html.twig
├── front/
│   └── ajax.php                     # get_attributes + get_field_value
├── js/dynamicfields.js              # comportamento designer + frontend
├── css/dynamicfields.css            # estilos do campo
└── patches/
    ├── apply.php                    # aplica/reverte/verifica o patch no Formcreator
    └── formcreator-2.13-fields.class.php.patch   # diff de referência
```

### Tabelas do banco de dados

Criadas no `install` (e removidas no `uninstall`) do plugin:

| Tabela | Conteúdo |
|---|---|
| `glpi_plugin_dynamicfields_sourcequestionparameters` | Parâmetro "Questão de origem" (coluna `values` = id da questão de origem) |
| `glpi_plugin_dynamicfields_attributeparameters` | Parâmetro "Atributo a exibir" (coluna `values` = nome do atributo) |

Ambas seguem a convenção de parâmetros do Formcreator:

```
id                              int UNSIGNED PK AUTO_INCREMENT
plugin_formcreator_questions_id int UNSIGNED  (FK lógica para a questão)
fieldname                       varchar(255)  ('source_question' | 'attribute')
values                          longtext      (valor do parâmetro)
uuid                            varchar(80)   (usado em export/import)
```

Há índices em `plugin_formcreator_questions_id` e na combinação `(plugin_formcreator_questions_id, fieldname)`.

### Endpoints AJAX

**`front/ajax.php`** — exige sessão autenticada; proteção CSRF via `Html::checkAjaxInput()` (GLPI 11) ou verificação manual de token (GLPI 10).

| `action` | Uso | Parâmetros | Resposta |
|---|---|---|---|
| `get_attributes` | Designer | `source_question_id`, `question_id` (opcional), `fieldtype` (opcional) | HTML do `<select>` de atributos (renderizado via Twig) |
| `get_field_value` | Usuário final | `source_question_id`, `items_id`, `attribute` | JSON `{"value": "...", "display": "..."}` |

### Registro do tipo de questão (patch)

O Formcreator não expõe hook oficial para registrar tipos de questão de outros plugins. O script `patches/apply.php` adiciona, em `plugins/formcreator/inc/fields.class.php`:

1. Um método público `getExternalTypes()` que agrega os tipos declarados pelo hook `formcreator_get_question_types`.
2. Chamadas a esse método dentro de `getTypes()`, `getClasses()` e `getFieldClassname()`.
3. Marcadores `// BEGIN dynamicfields` / `// END dynamicfields` para aplicação/reversão confiável.

O hook é usado pelo `hook.php` deste plugin:

```php
// hook.php
function plugin_dynamicfields_get_question_types() {
   return [
      'dynamic' => [
         'classname' => 'PluginDynamicfieldsField',
         'file'      => PLUGIN_DYNAMICFIELDS_ROOT . '/inc/field.class.php',
      ],
   ];
}
```

O `setup.php` valida o estado do patch nos pré-requisitos (`PluginFormcreatorFields::getExternalTypes`).

### Atributos exibidos

Em `PluginDynamicfieldsHelper::getAttributesForItemtype($itemtype)`:

1. Usa os **search options** do itemtype (`getSearchOptions()`), filtrando apenas colunas da **tabela principal** do item.
2. Remove `datatype` sensíveis: `password`, `passwd`, `file`, `files`, `image`.
3. Remove colunas em `SENSITIVE_COLUMNS`: `passwd`, `password`, `api_token`, `cookie_token`, `session_token`, `glpiauthsecret`, `pin`.
4. Remove colunas em `SYSTEM_COLUMNS`: `id`, `entities_id`, `is_recursive`, `is_deleted`, `is_template`, `template`.
5. Fallback: se não houver search options, usa as colunas brutas da tabela principal (com o mesmo filtro de sensíveis/sistema).

A resolução de valor (`resolveAttributeValue`) lê a coluna direto do item; se o atributo não for coluna pura (ex.: e-mail de um usuário que vive em outra tabela), resolve via `Search` do GLPI.

---

## FAQ / Solução de problemas

**O campo dinâmico aparece vazio no formulário.** Confira que a origem foi selecionada e o atributo configurado no designer; o preenchimento acontece ao **alterar** a questão de origem. Se o valor era pré-preenchido (default answer), o JS tenta resolver na carga da página.

**O valor não aparece no ticket.** O valor é salvo em `formcreator_field_{id}` na resposta do formulário. Verifique se o ticket usa `##FULLFORM##`.

**O select "Atributo a exibir" fica vazio.** Nenhuma coluna exibível encontrada para o itemtype da origem (ou a origem não é suportada). Colunas sensíveis/de sistema são ocultadas por design.

**Depois de atualizar o Formcreator, o tipo de questão sumiu.** O upgrade sobrescreve `inc/fields.class.php`. Reexecute `php plugins/dynamicfields/patches/apply.php`.

---

## Desenvolvimento e contribuição

* **Modelo dos arquivos**: siga a convenção de classes do Formcreator (extender `PluginFormcreatorAbstractField` / `PluginFormcreatorAbstractQuestionParameter`).
* **Testes manuais sugeridos**: instalar em GLPI 10 + Formcreator 2.13, criar formulário com origem + campo dinâmico, submeter resposta e conferir o ticket.
* **Bugs e melhorias**: abra uma issue descrevendo o ambiente (versões do GLPI/Formcreator), o passo a passo e o resultado esperado vs obtido.

---

## Licença

GPL-2.0-or-later — veja os cabeçalhos dos arquivos e o arquivo `LICENSE`.