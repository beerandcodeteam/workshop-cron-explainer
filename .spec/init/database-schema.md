# Cron Explainer — Database Schema

<!-- inputs: project-description.md@sha256:7025602f3bcc user-stories.md@sha256:47d3d24e8d3c -->

## Overview

**Cron Explainer não tem modelo de dados de domínio.** A decisão do desenvolvedor é explícita: *"não se aplica, não precisa de novas tabelas"*. Cada requisição é independente — o visitante envia uma expressão cron, a IA devolve explicação e datas, a tela renderiza, e nada é gravado. Não há histórico, não há conta de usuário, não há cache de resultado. **Zero tabelas novas, zero migrations novas, zero models de domínio.**

O bloco DBML abaixo existe apenas para registrar o que **já está no repositório** e permanece **intocado**: as tabelas de infraestrutura do starter kit Laravel (`0001_01_01_*`). Elas não participam de nenhum workflow ou user story do Cron Explainer — a única rota da aplicação nunca lê nem escreve nelas. Ficam documentadas para que a próxima pessoa saiba que são herança do skeleton, não modelagem deste projeto.

Conexão em uso: **SQLite** (`DB_CONNECTION=sqlite`, arquivo local). O `compose.yaml` não sobe container de banco — coerente com "tudo local". Convenções em vigor caso alguma tabela venha a existir no futuro: **Eloquent / Laravel 13** — nomes plurais em snake_case, `id` autoincremental, FKs `<singular>_id`, `created_at`/`updated_at`, e **tabelas de lookup no lugar de colunas enum**.

## Schema (DBML)

```dbml
// ============================================================
// NENHUMA TABELA DE DOMÍNIO. A aplicação é stateless.
// O que segue é infraestrutura herdada do starter kit Laravel,
// declarada aqui apenas como registro do estado atual do repo.
// Nenhuma story do Cron Explainer lê ou escreve nestas tabelas.
// ============================================================

// --- Auth do skeleton (não usado: a página é pública e anônima) ---

Table users {
  id bigint [pk, increment]
  name varchar [not null]
  email varchar [unique, not null]
  email_verified_at timestamp [null]
  password varchar [not null]
  remember_token varchar(100) [null]
  created_at timestamp
  updated_at timestamp

  Note: 'Herdado do starter kit. Sem cadastro nem login no MVP.'
}

Table password_reset_tokens {
  email varchar [pk]
  token varchar [not null]
  created_at timestamp [null]

  Note: 'Herdado do starter kit. Sem fluxo de recuperação de senha no MVP.'
}

Table sessions {
  id varchar [pk]
  user_id bigint [ref: > users.id, null]
  ip_address varchar(45) [null]
  user_agent text [null]
  payload longtext [not null]
  last_activity integer [not null, note: 'indexado']

  Note: 'Herdado do starter kit. Livewire usa a sessão do framework; nenhum dado de domínio trafega aqui.'
}

// --- Cache do framework (não usado: nenhum resultado de IA é cacheado) ---

Table cache {
  key varchar [pk]
  value mediumtext [not null]
  expiration bigint [not null, note: 'indexado']
}

Table cache_locks {
  key varchar [pk]
  owner varchar [not null]
  expiration bigint [not null, note: 'indexado']
}

// --- Filas do framework (não usadas: a chamada à IA é síncrona) ---

Table jobs {
  id bigint [pk, increment]
  queue varchar [not null, note: 'indexado']
  payload longtext [not null]
  attempts smallint [not null]
  reserved_at integer [null]
  available_at integer [not null]
  created_at integer [not null]
}

Table job_batches {
  id varchar [pk]
  name varchar [not null]
  total_jobs integer [not null]
  pending_jobs integer [not null]
  failed_jobs integer [not null]
  failed_job_ids longtext [not null]
  options mediumtext [null]
  cancelled_at integer [null]
  created_at integer [not null]
  finished_at integer [null]
}

Table failed_jobs {
  id bigint [pk, increment]
  uuid varchar [unique, not null]
  connection varchar [not null]
  queue varchar [not null]
  payload longtext [not null]
  exception longtext [not null]
  failed_at timestamp [not null, default: `CURRENT_TIMESTAMP`]
}
```

## Relationships

- **Nenhuma relação de domínio existe** — não há entidades de domínio para relacionar.
- Uma **session** pode pertencer a um **user** (`sessions.user_id`, nullable, sem constraint de FK no schema do Laravel). Herança do starter kit; nunca populada com `user_id` neste projeto, já que não há autenticação.
- As demais tabelas (`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`) são **isoladas**, sem relação entre si nem com `users`.

## Lookup Table Seeds

**Nenhuma.** Não existe tabela de lookup porque não existe campo categórico persistido. O único campo categórico do domínio é o booleano `valido` do structured output da IA — e ele vive na resposta em memória, não em coluna.

## Notes & Conventions

- **Soft deletes:** nenhuma tabela usa `deleted_at`. Não há entidade a excluir.
- **Pivots:** nenhum.
- **Índices:** os do starter kit permanecem como estão (`sessions.last_activity`, `sessions.user_id`, `cache.expiration`, `cache_locks.expiration`, `jobs.queue`, `failed_jobs[connection,queue,failed_at]`). Nada a acrescentar.
- **Migrations novas: zero.** Este documento não gera trabalho de migration na fase de build.
- **Migrations do AI SDK:** o `vendor:publish` de `laravel/ai` registra migrations próprias (persistência de conversas). A intenção declarada é **não rodá-las** — o agente é usado de forma stateless. Se `prompt()` com structured output exigir essas tabelas, o SQLite local absorve o caso sem alterar este schema de domínio (que continua vazio) e sem introduzir container de banco.

**Cobertura dos Key Concepts — todos não persistidos:**

- **Expressão cron** — não persistida: input transitório do formulário Livewire, vive na propriedade do componente durante a requisição.
- **Explicação** — não persistida: campo da resposta da IA, renderizado e descartado.
- **Próximas execuções** — não persistidas: array da resposta da IA, calculado a cada chamada a partir do `now()` da requisição. Persistir seria errado, já que envelhecem.
- **Validade** — não persistida: booleano do structured output, decide qual bloco a view renderiza.
- **Agente** — não persistido: classe PHP em `app/Ai/Agents/`, código e não dado.
- **Structured output** — não persistido: contrato JSON validado pelo SDK em memória.
- **Sem estado** — é a própria decisão que justifica este documento vazio de domínio.
- *(Os itens "Precisão das datas", "Identificador do modelo" e "Migrations do AI SDK" da descrição são open questions, não conceitos de domínio — o identificador do modelo vive em `config/ai.php`/`.env`, nunca em tabela.)*
