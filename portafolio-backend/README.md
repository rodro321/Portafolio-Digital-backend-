# Portafolio Digital — Backend (Laravel 8 + PostgreSQL)

API REST para el sistema generador de portafolios digitales.

## Requisitos

- **PHP** 7.4+ (con extensiones: pdo_pgsql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath)
- **Composer** 2.x
- **PostgreSQL** 12+
- **Node.js** 16+ (solo si se usa el frontend)

## Instalación rápida

```bash
# 1. Clonar el repositorio
git clone <url-del-repo>
cd portafolio-backend

# 2. Instalar dependencias PHP
composer install

# 3. Copiar archivo de entorno y configurar
cp .env.example .env
# Editar .env con tus credenciales de BD y GitHub OAuth

# 4. Generar APP_KEY
php artisan key:generate

# 5. Crear la base de datos en PostgreSQL
# Ejecutar el script SQL completo (tablas + stored procedures + datos iniciales)

# 6. Crear el enlace simbólico de storage (para fotos)
php artisan storage:link

# 7. Iniciar el servidor de desarrollo
php artisan serve
```

El backend estará disponible en `http://127.0.0.1:8000`

## Variables de entorno importantes

| Variable | Descripción | Ejemplo |
|---|---|---|
| `DB_PASSWORD` | Contraseña de PostgreSQL | tu_password |
| `GITHUB_CLIENT_ID` | ID de OAuth App en GitHub | `Ov23li...` |
| `GITHUB_CLIENT_SECRET` | Secret de OAuth App | `2f5541...` |
| `FRONTEND_URL` | URL del frontend (CORS) | `http://localhost:3000` |

### Configurar GitHub OAuth

1. Ir a [GitHub Developer Settings](https://github.com/settings/developers)
2. Crear una nueva **OAuth App**
3. **Homepage URL**: `http://localhost:3000`
4. **Authorization callback URL**: `http://localhost:8000/api/auth/github/callback`
5. Copiar el Client ID y Client Secret al `.env`

## Endpoints principales

### Auth
| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/auth/registro` | Registro de usuario |
| POST | `/api/auth/login` | Login con email/password |
| POST | `/api/auth/logout` | Cerrar sesión (requiere token) |
| GET | `/api/auth/me` | Datos del usuario autenticado |
| GET | `/api/auth/github` | Iniciar OAuth con GitHub |
| GET | `/api/auth/github/callback` | Callback de GitHub |

### Perfil
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/usuario/perfil` | Obtener perfil |
| PUT | `/api/usuario/perfil` | Actualizar perfil |
| POST | `/api/usuario/foto` | Subir foto (form-data) |

### Habilidades
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/habilidades/catalogo` | Catálogo público |
| GET | `/api/habilidades` | Mis habilidades |
| POST | `/api/habilidades` | Agregar del catálogo |
| PUT | `/api/habilidades/sincronizar` | Sincronizar en bloque |

### Proyectos
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/proyectos` | Listar proyectos |
| POST | `/api/proyectos` | Crear proyecto |
| PUT | `/api/proyectos/{id}` | Actualizar proyecto |
| DELETE | `/api/proyectos/{id}` | Eliminar proyecto |
| POST | `/api/proyectos/{id}/imagenes` | Subir imagen |

## Autenticación

Todas las rutas protegidas requieren el header:
```
Authorization: Bearer <token>
```

El token se obtiene en `/api/auth/login` o `/api/auth/registro`.

## Notas para el equipo

- **NO commitear el archivo `.env`** — contiene secretos. Usar `.env.example` como plantilla.
- **NO ejecutar `config:cache` ni `route:cache` en desarrollo** — causa que los cambios al `.env` no se reflejen.
- Si ves errores 401, verifica que el token sea válido con `GET /api/auth/me`.
- Si ves errores CORS, verifica que `FRONTEND_URL` en `.env` coincida con la URL donde corre tu frontend.
