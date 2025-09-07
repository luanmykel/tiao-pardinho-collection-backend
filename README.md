# Collection Tião Carreiro & Pardinho – Backend (v2)

O **Top 5 Tião Carreiro**, evoluiu para a v2: agora **Collection Tião Carreiro & Pardinho** 😁,  
com uma API REST em **Laravel 11**, autenticação JWT e integração com o frontend.

---

### Frameworks e Bibliotecas

- **Laravel 11** – Base do backend.
- **PHP 8.2+** – Linguagem de execução.
- **JWT Auth (php-open-source-saver/jwt-auth)** – Autenticação via tokens JWT.
- **FakerPHP** – Geração de dados fake para testes e seeds.
- **SQLite** – Banco padrão (simples e prático).
- **PostgreSQL / MySQL** – Suporte opcional (configurável via .env).
- **Redis** – Cache opcional (configurável via .env).
- **PHPUnit** – Testes automatizados.
- **Laravel Pint** – Padronização de código (PSR-12).
- **Laravel Sail / Docker** – Ambiente containerizado.

---

## Estrutura

- **Autenticação**
    - JWT com refresh automático e rotação de tokens.
    - Persistência configurável (memória/local storage no frontend).
    - Middleware de proteção para rotas privadas.

- **Seeds Iniciais**
    - Já inclui **36 músicas pré-cadastradas**.
    - Usuário administrador padrão:
      ```
      Usuário: admin@teste.com
      Senha:   secret123
      ```

- **Scraper Automático**
    - Integração com YouTube para atualizar **título** e **visualizações**.
    - Executado na seed inicial para popular metadados.
    - Middleware de cache com expiração a cada 15 dias para revalidar visualizações.

- **Sugestões**
    - Usuários podem sugerir músicas pelo frontend.
    - Admin pode aprovar, reprovar ou excluir sugestões.

- **Administração**
    - Gestão de músicas, sugestões, usuários e perfis.
    - API estruturada para suportar o painel administrativo do frontend.

---

## Desenvolvimento Local

#### Requisitos
PHP 8.2+ e Composer 2.x

```bash
git clone https://github.com/luanmykel/tiao-pardinho-collection-backend.git

cp .env.example .env

composer install
php artisan key:generate
php artisan jwt:secret

php artisan migrate --seed
php artisan storage:link
php artisan serve
````

( se necessário, ajustar a url no .env )

### Testes

```bash
php artisan test
```

Ou simplesmente 😊

### Docker (Backend + Frontend)

#### Requisitos
Docker 24+ e Docker Compose v2+

```bash
git clone https://github.com/luanmykel/tiao-pardinho-collection-backend.git

docker compose up --build
# ou em segundo plano
docker compose up --build -d
```

- Acesso: http://localhost:8080/
- Admin: http://localhost:8080/admin
````
Usuário: admin@teste.com
Senha:   secret123
````

## Live Demo

- **[Collection Tião Carreiro & Pardinho](https://collection.lmdev.space/)**
- **[Collection Tião Carreiro & Pardinho - Admin](https://collection.lmdev.space/admin)**

### Credenciais de Acesso

```
Usuário: admin@teste.com
Senha:   secret123
```
