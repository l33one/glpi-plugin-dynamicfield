# GEMINI_IMAGES.md

Descrições completas das **6 imagens** da tela de configuração (manual visual) do plugin
**Campos Dinâmicos Formulário** (`dynamicfields`).

> **Como usar:** peça ao Gemini para gerar cada imagem usando o **"Prompt"** da seção
> correspondente. Salve cada arquivo **exatamente** no caminho indicado (pasta
> `public/templates/config/images/` do projeto). Os nomes de arquivo são finais — o template
> Twig os referencia por esses nomes.

---

## Guia de estilo (vale para todas as imagens)

- **Visual:** UI mockup/flat moderna, **limpa** e com **cores suaves** (estilo GLPI 10/11).
- **Paleta:**
  - Indigo principal: `#5b6ff0`
  - Ciano: `#2bb3d6`
  - Violeta: `#8b6fe6`
  - Âmbar: `#e8a13c`
  - Verde: `#3fbf8f`
  - Fundo suave: `#f6f8ff`
  - Cartões: branco `#ffffff`
  - Texto: `#3d4a63` (cinza-azulado); texto secundário `#7b88a3`
  - Bordas: `#e8ecf7`
- **Formas:** cartões com bordas arredondadas (raio 12–16 px), sombras suaves
  (`0 10px 28px rgba(80,100,180,.10)`), espaçamento generoso.
- **Tipografia:** fonte sem serifa (similar a Inter/Roboto), textos **em português do Brasil**.
- **Regras obrigatórias:**
  - Nada de telas reais (não simular fotografia de GLPI real) — sempre ilustração flat de UI.
  - Nenhum logo, nome real de empresa, dado sensível ou número de série real.
  - Sem elementos ambíguos/ilegíveis: os textos dentro das imagens devem estar nítidos e corretos.
  - Alinhar elementos com consistência (mesmo espaçamento, mesma família de cartões em todas as imagens).

---

## 1. `hero-diagram.png` — Diagrama conceitual (banner do topo)

- **Caminho:** `public/templates/config/images/hero-diagram.png`
- **Formato:** PNG, paisagem, **1600 × 900 px**.
- **Objetivo no manual:** ilustra a ideia central do plugin: uma questão de origem
  (ex.: "Qual computador?") alimenta o **Campo Dinâmico**, que exibe um atributo
  automaticamente.

### Descrição visual

Fundo com gradiente suave de **indigo → violeta → verde bem claros**
(`#f2f5ff → #f9f3ff → #f0fbf6`), quase pastel. No centro, um fluxo horizontal com 3 blocos:

1. **Cartão esquerdo — "Questão de origem"** (borda `#e8ecf7`, fundo branco):
   - Título pequeno: **"Qual computador?"** com ícone azul de computador.
   - Abaixo, um *select* estilizado com o valor selecionado: **"PC-SALA-12 · Dell OptiPlex 5090"**
     e uma etiqueta/label ao lado: **"Questão: Objeto do GLPI"** em chip ciano suave.
2. **Setas no centro:** duas setas largas, suaves, verdes (`#3fbf8f`), apontando
   da esquerda para a direita, representando o preenchimento automático.
3. **Cartão direito — "Campo Dinâmico"** (fundo branco, borda suave):
   - Título: **"Número de série"** com ícone de etiqueta/ID.
   - Campo de texto *read-only* (estilo desabilitado, fundo `#f6f8ff`, borda tracejada)
     contendo: **"9D4K2-MNP-QRS7"**.
   - Chip verde ao lado do campo: **"Automático"** (badge arredondado).

### Prompt (copie e cole no Gemini)

> Gere uma ilustração flat em estilo de UI mockup, formato paisagem 1600x900. Fundo com
> gradiente suave pastel de indigo a verde-claro (#f2f5ff → #f9f3ff → #f0fbf6). No centro,
> um fluxo horizontal com três elementos: (1) à esquerda um cartão branco arredondado de
> borda cinza suave com o título "Qual computador?" e um ícone de computador azul (#5b6ff0),
> abaixo um campo de seleção estilizado preenchido com "PC-SALA-12 · Dell OptiPlex 5090" e um
> chip ciano com o texto "Questão: Objeto do GLPI"; (2) no meio duas setas verdes (#3fbf8f)
> largas apontando para a direita; (3) à direita um cartão branco arredondado com o título
> "Número de série" e ícone de etiqueta, um campo de texto desabilitado (fundo #f6f8ff, borda
> tracejada) contendo "9D4K2-MNP-QRS7", e um chip verde arredondado com o texto "Automático".
> Textos em português do Brasil, sem logo de empresa, sem dados reais, cantos arredondados,
> sombras suaves, visual limpo e moderno.

---

## 2. `designer.png` — Designer do Formcreator (adicionando o campo)

- **Caminho:** `public/templates/config/images/designer.png`
- **Formato:** PNG, paisagem, **1600 × 1000 px**.
- **Objetivo no manual:** mostra onde o administrador adiciona a pergunta do tipo
  **"Campo Dinâmico"** no designer de formulário do Formcreator (Form Creator → Forms).

### Descrição visual

Layout de tela de designer, com **barra de topo** e **duas colunas**:

- **Barra de topo:** breadcrumb **"Forms" > "Abertura de chamado - TI"**, botão **"Salvar"**
  (verde) à direita.
- **Coluna esquerda (lista de perguntas do formulário):**
  - Item **"Qual computador?"** — ícone azul de computador (questão "Objeto do GLPI").
  - Item **"Número de série"** — **selecionado/destacado** (borda indigo `#5b6ff0`, fundo
    `#eef0ff`), com ícone de raio roxo; ao lado pequeno chip **"Campo Dinâmico"**.
  - Item **"Descrição do problema"** — ícone de texto.
- **Coluna direita (painel "Detalhes da pergunta"):**
  - Título do campo: **"Número de série"**.
  - Campo rotulado **"Tipo de questão"**: *select* estilizado mostrando **"Campo Dinâmico"**
    aberto com uma lista de opções (Glpi select, Dropdown, Text, Campo Dinâmico — sendo a
    última **destacada em indigo**).
  - Abaixo, área em *placeholder*: **"Configure via parâmetros abaixo (Questão de origem /
    Atributo a exibir)"** em texto cinza.

### Prompt (copie e cole no Gemini)

> Ilustração flat de UI mockup, paisagem 1600x1000, da tela do designer de formulários do
> GLPI Formcreator. Barra de topo com breadcrumb "Forms > Abertura de chamado - TI" e botão
> "Salvar" verde à direita. Duas colunas: à esquerda, uma lista de perguntas com três itens:
> "Qual computador?" (ícone de computador azul #5b6ff0), "Número de série" (selecionado, com
> borda indigo #5b6ff0, fundo #eef0ff, ícone de raio roxo e chip "Campo Dinâmico"), e
> "Descrição do problema" (ícone de texto). À direita, o painel "Detalhes da pergunta" com o
> campo "Tipo de questão" exibindo um select aberto onde "Campo Dinâmico" aparece destacado em
> indigo sobre outras opções (Glpi select, Dropdown, Text). Abaixo um texto cinza em
> placeholder: "Configure via parâmetros abaixo (Questão de origem / Atributo a exibir)".
> Textos em português do Brasil, cantos arredondados (12–16px), sombras suaves
> (rgba(80,100,180,.10)), fundo #f6f8ff, visual limpo e moderno.

---

## 3. `source.png` — Parâmetro "Questão de origem"

- **Caminho:** `public/templates/config/images/source.png`
- **Formato:** PNG, paisagem, **1400 × 900 px**.
- **Objetivo no manual:** mostra o parâmetro **"Questão de origem"** (Pergunta de origem),
  com a lista de questões anteriores compatíveis.

### Descrição visual

Cartão centralizado (fundo branco, borda `#e8ecf7`, raio 16 px, sombra suave) com:

- Título no topo: **"Questão de origem"** com ícone de mouse/apontador ciano.
- Corpo com o *select* **aberto** (lista suspensa exibida):
  - Opção 1: **"[Computer] Qual computador?"** — **destacada/selecionada** (fundo `#eaf8fd`,
    borda ciano `#2bb3d6`, com um "check" azul à esquerda).
  - Opção 2 (desabilitada, cinza): **"[Computer] Local da instalação?"**
  - Rótulo/descrição abaixo do select: **"Somente questões anteriores compatíveis são
    listadas."** em cinza.
- Cantos com dica (*tooltip*) suave ciano: **"Se houver só uma origem, ela é pré-selecionada
  automaticamente."**

### Prompt (copie e cole no Gemini)

> Ilustração flat de UI mockup, paisagem 1400x900. Um cartão branco centralizado (borda
> #e8ecf7, raio 16px, sombra suave rgba(80,100,180,.10)). Título "Questão de origem" com ícone
> de mouse/apontador em ciano (#2bb3d6). Um campo de seleção estilizado ABERTO com a lista
> suspensa: a opção "[Computer] Qual computador?" destacada (fundo #eaf8fd, borda ciano e um
> check azul à esquerda) sobre uma segunda opção desabilitada "[Computer] Local da
> instalação?" em cinza. Abaixo, texto pequeno cinza: "Somente questões anteriores
> compatíveis são listadas." e um balão de dica suave ciano com o texto "Se houver só uma
> origem, ela é pré-selecionada automaticamente." Textos em português do Brasil, cantos
> arredondados, sombras suaves, fundo #f6f8ff, visual limpo e moderno.

---

## 4. `attribute.png` — Parâmetro "Atributo a exibir"

- **Caminho:** `public/templates/config/images/attribute.png`
- **Formato:** PNG, paisagem, **1400 × 900 px**.
- **Objetivo no manual:** mostra o parâmetro **"Atributo a exibir"** com a lista de colunas
  do itemtype da origem (ex.: Computer).

### Descrição visual

Cartão centralizado (mesmo estilo da imagem 3) com:

- Título: **"Atributo a exibir"** com ícone de lista violeta (`#8b6fe6`).
- *Select* **aberto** com as colunas do item de origem:
  - **Nome (name)** — ícone de etiqueta
  - **Número de série (serial)** — **selecionada** (fundo `#f1ecff`, borda violeta `#8b6fe6`,
    check à esquerda)
  - **Número de patrimônio (otherserial)**
  - **Localização (locations_id)**
  - **Status (states_id)**
- Abaixo, texto cinza: **"Colunas sensíveis (senhas, tokens) e de sistema são removidas
  automaticamente."**

### Prompt (copie e cole no Gemini)

> Ilustração flat de UI mockup, paisagem 1400x900. Cartão branco centralizado (borda #e8ecf7,
> raio 16px, sombra suave). Título "Atributo a exibir" com ícone de lista em violeta (#8b6fe6).
> Campo de seleção estilizado ABERTO listando as colunas de um computador: "Nome (name)",
> "Número de série (serial)" (selecionada — fundo #f1ecff, borda violeta #8b6fe6, check à
> esquerda), "Número de patrimônio (otherserial)", "Localização (locations_id)" e "Status
> (states_id)", cada uma com um pequeno ícone. Abaixo, texto pequeno cinza: "Colunas sensíveis
> (senhas, tokens) e de sistema são removidas automaticamente." Textos em português do Brasil,
> cantos arredondados, sombras suaves, fundo #f6f8ff, visual limpo e moderno.

---

## 5. `runtime.png` — Resultado no formulário em execução

- **Caminho:** `public/templates/config/images/runtime.png`
- **Formato:** PNG, paisagem, **1600 × 900 px**.
- **Objetivo no manual:** mostra o que o *requerente* vê no formulário final: a origem
  selecionada e o **Campo Dinâmico preenchido automaticamente** (somente-leitura).

### Descrição visual

Mockup de um formulário de chamado aberto, em uma cartão central (fundo branco):

- **Título do formulário:** **"Abertura de chamado - TI"** (topo).
- **Pergunta 1 (Questão de origem):** label **"Qual computador?"** — *select* estilizado
  preenchido com **"PC-SALA-12 · Dell OptiPlex 5090"**, ícone azul de computador. Lado direito
  um *chip verde*: **"Respondida"**.
- **Pergunta 2 (Campo Dinâmico):** label **"Número de série"** — campo de texto **read-only**
  (fundo `#f6f8ff`, borda tracejada cinza) preenchido com **"9D4K2-MNP-QRS7"**. Chip verde ao
  lado: **"Automático"** com ícone de cadeado pequeno. Ícone de cadeado ao lado do label
  indicando que o campo não é editável.
- **Botão** "Enviar" (verde) no rodapé do formulário.

### Prompt (copie e cole no Gemini)

> Ilustração flat de UI mockup, paisagem 1600x900, de um formulário GLPI em execução. Cartão
> branco centralizado com o título "Abertura de chamado - TI". Duas perguntas lado a lado:
> primeira, label "Qual computador?" com um campo de seleção preenchido com "PC-SALA-12 · Dell
> OptiPlex 5090" e ícone de computador azul (#5b6ff0), com chip verde "Respondida" ao lado;
> segunda, label "Número de série" com campo de texto read-only (fundo #f6f8ff, borda tracejada
> #e8ecf7) preenchido com "9D4K2-MNP-QRS7", chip verde "Automático" com ícone de cadeado
> pequeno, e cadeado ao lado do label indicando campo não editável. Botão "Enviar" verde no
> rodapé. Textos em português do Brasil, cantos arredondados (12–16px), sombras suaves
> (rgba(80,100,180,.10)), fundo #f6f8ff, visual limpo e moderno.

---

## 6. `order.png` — Ordem correta da questão de origem

- **Caminho:** `public/templates/config/images/order.png`
- **Formato:** PNG, paisagem, **1400 × 900 px**.
- **Objetivo no manual:** mostra a regra de **ordem**: a questão de origem deve estar
  **acima** do campo dinâmico; se ficar **abaixo**, é ignorada.

### Descrição visual

Dois painéis lado a lado, sobre o fundo suave `#f6f8ff`:

- **Painel esquerdo — "Origem acima" (correto):** borda verde (`#3fbf8f`), fundo `#e9f9f2`,
  check verde no topo.
  - Lista vertical com duas perguntas:
    1. **"Qual computador?"** (ícone azul) — com seta pequena verde "↓ origina" apontando para baixo.
    2. **"Número de série"** (ícone roxo) — chip **"Campo Dinâmico"**.
- **Painel direito — "Origem abaixo" (incorreto):** borda âmbar/vermelho suave (`#e8a13c`),
  fundo `#fdf3e3`, "X" âmbar no topo.
  - Lista vertical invertida:
    1. **"Número de série"** (ícone roxo) — chip **"Campo Dinâmico"**.
    2. **"Qual computador?"** (ícone azul) — etiqueta **"sem efeito (não é origem)"** em cinza.

### Prompt (copie e cole no Gemini)

> Ilustração flat de UI mockup, paisagem 1400x900, de um diagrama comparativo em dois painéis
> lado a lado sobre fundo #f6f8ff. Painel esquerdo intitulado "Origem acima (correto)" com
> borda verde (#3fbf8f), fundo #e9f9f2 e um símbolo de check verde no topo; contém uma lista
> vertical de duas perguntas: "Qual computador?" (ícone de computador azul, com uma seta verde
> pequena apontando para baixo com o rótulo "origina") e, abaixo, "Número de série" (ícone roxo)
> com um chip "Campo Dinâmico". Painel direito intitulado "Origem abaixo (incorreto)" com borda
> âmbar (#e8a13c), fundo #fdf3e3 e um "X" âmbar no topo; contém a lista invertida: "Número de
> série" (ícone roxo, chip "Campo Dinâmico") e, abaixo, "Qual computador?" (ícone azul) com
> etiqueta cinza "sem efeito (não é origem)". Textos em português do Brasil, cantos
> arredondados (12–16px), sombras suaves, visual limpo e moderno.

---

## Checklist final

- [ ] `public/templates/config/images/hero-diagram.png` (1600×900)
- [ ] `public/templates/config/images/designer.png` (1600×1000)
- [ ] `public/templates/config/images/source.png` (1400×900)
- [ ] `public/templates/config/images/attribute.png` (1400×900)
- [ ] `public/templates/config/images/runtime.png` (1600×900)
- [ ] `public/templates/config/images/order.png` (1400×900)

Todos dentro da pasta `public/templates/config/images/` do projeto. Após salvar, recarregue a
página **Plugins → Configure** do *Campos Dinâmicos Formulário* para conferir.