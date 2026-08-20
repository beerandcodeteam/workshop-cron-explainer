# Cron Explainer — User Stories

<!-- inputs: project-description.md@sha256:7025602f3bcc -->

## Overview

**Cron Explainer** é uma página única e pública que traduz uma expressão cron para português e mostra quando ela vai rodar de verdade. O visitante cola `0 3 * * 1-5`, clica em **Explicar**, e recebe uma explicação em linguagem natural mais as **5 próximas execuções** em `America/Sao_Paulo`.

Não há login, cadastro nem persistência. Toda a interpretação — significado, datas e detecção de erro — vem de **uma única chamada ao Laravel AI SDK com structured output**. Isso concentra o backlog em três coisas: o caminho feliz, o erro explicado e a resiliência quando a IA não responde.

**User Types:**
- **Visitante** - qualquer pessoa que abre a página, sem autenticação; escreve ou revisa crontab e quer entender uma expressão sem consultar documentação.

---

## 1. Explicação de expressão cron

### US-1.1: Explicar uma expressão cron válida
**As a** Visitante
**I want to** colar uma expressão cron e receber sua explicação em português
**So that** eu entenda a regra de disparo sem decorar a ordem dos cinco campos

**Acceptance Criteria:**
- [ ] A rota `/` renderiza um único campo de texto com placeholder `0 3 * * 1-5` e um botão **Explicar**
- [ ] Submeter `*/15 2-6 * * 0` produz uma explicação em pt-BR descrevendo minuto, hora e dia da semana da regra
- [ ] A explicação é exibida em destaque, acima da lista de execuções
- [ ] Expressões com `*`, valores, listas (`1,15`), intervalos (`1-5`) e passos (`*/15`) são aceitas
- [ ] Macros `@daily`, `@hourly`, `@weekly`, `@monthly` e `@yearly` são aceitas e explicadas
- [ ] Uma nova submissão substitui integralmente o resultado anterior na tela
- [ ] Nada é gravado: recarregar a página limpa o resultado

**Expected Result:** O visitante lê, em uma frase em português, o que a expressão faz.

---

### US-1.2: Ver as 5 próximas execuções
**As a** Visitante
**I want to** ver datas e horas concretas das próximas execuções
**So that** eu confirme que a expressão dispara quando eu espero

**Acceptance Criteria:**
- [ ] São exibidas **exatamente 5** execuções futuras, em ordem cronológica crescente
- [ ] Todas as datas usam o timezone fixo `America/Sao_Paulo`
- [ ] A contagem parte do instante da requisição — nenhuma data no passado é listada
- [ ] Cada item mostra o dia da semana por extenso além da data e hora (ex: `domingo, 23/08/2026 02:15`)
- [ ] O instante atual em ISO-8601 e o timezone são enviados no prompt junto da expressão
- [ ] Se `valido` for `true`, o array `proximas_execucoes` tem 5 itens; caso contrário a lista não é renderizada

**Expected Result:** O visitante vê cinco horários concretos e futuros para a expressão informada.

---

### US-1.3: Acompanhar o estado de carregamento
**As a** Visitante
**I want to** saber que o pedido está sendo processado
**So that** eu não clique várias vezes achando que a página travou

**Acceptance Criteria:**
- [ ] Ao submeter, o botão **Explicar** fica desabilitado via `wire:loading.attr="disabled"`
- [ ] Um indicador de carregamento (spinner ou texto) aparece enquanto a chamada à IA está em curso
- [ ] O resultado chega de uma vez, sem streaming token a token
- [ ] Cliques repetidos durante o carregamento não disparam chamadas adicionais à IA
- [ ] Ao concluir (sucesso ou erro), o indicador some e o botão volta a ficar habilitado

**Expected Result:** O visitante tem feedback visual contínuo entre o clique e a resposta.

---

## 2. Tratamento de erros

### US-2.1: Receber erro explicado para expressão inválida
**As a** Visitante
**I want to** saber exatamente qual campo da minha expressão está errado
**So that** eu corrija sem ir procurar a documentação do cron

**Acceptance Criteria:**
- [ ] Não há validação prévia por regex — qualquer texto não vazio é enviado à IA
- [ ] Para `70 3 * * *`, a resposta traz `valido: false` e um erro citando o campo e o intervalo aceito (ex: "o campo minuto aceita 0–59, mas recebeu 70")
- [ ] Quando `valido` é `false`, a lista de próximas execuções **não** é renderizada
- [ ] O erro é exibido em estado visual de alerta, distinto do estado de sucesso
- [ ] O texto digitado permanece no campo para correção

**Expected Result:** O visitante lê uma mensagem que aponta o campo problemático e o intervalo válido.

---

### US-2.2: Ser bloqueado localmente em input vazio
**As a** Visitante
**I want to** ser avisado sem espera quando não digitei nada
**So that** eu não gaste tempo com uma chamada à IA que não faz sentido

**Acceptance Criteria:**
- [ ] Campo vazio bloqueia a submissão e exibe mensagem local
- [ ] Campo contendo apenas espaços em branco é tratado como vazio
- [ ] Nenhuma chamada à IA é disparada nesses casos
- [ ] A mensagem local desaparece assim que o visitante digita algo válido

**Expected Result:** Submissões vazias são barradas no cliente, sem custo de API.

---

### US-2.3: Falhar com graça quando a IA não responde
**As a** Visitante
**I want to** ver uma mensagem clara quando o serviço de IA falha
**So that** eu saiba que o problema não é a minha expressão e possa tentar de novo

**Acceptance Criteria:**
- [ ] A chamada ao agente roda dentro de `try/catch` com `timeout` explícito
- [ ] Falha de rede, timeout, chave ausente, chave inválida e payload fora do schema produzem a **mesma** mensagem genérica na tela
- [ ] A tela nunca exibe stack trace, nome de exception, nem qualquer parte da `OPENAI_API_KEY`
- [ ] O detalhe técnico da falha é registrado em `storage/logs`
- [ ] O texto digitado pelo visitante é preservado no campo para nova tentativa
- [ ] Com `APP_DEBUG=true` a tela continua mostrando a mensagem genérica, não a página de erro do Laravel

**Expected Result:** Qualquer falha da IA vira uma mensagem única e segura, com o input preservado.

---

## Appendix: User Story Status

| ID | Story | Priority | Status |
|----|-------|----------|--------|
| US-1.1 | Explicar uma expressão cron válida | High | Pending |
| US-1.2 | Ver as 5 próximas execuções | High | Pending |
| US-2.1 | Receber erro explicado para expressão inválida | High | Pending |
| US-2.3 | Falhar com graça quando a IA não responde | High | Pending |
| US-1.3 | Acompanhar o estado de carregamento | Medium | Pending |
| US-2.2 | Ser bloqueado localmente em input vazio | Medium | Pending |
