
<p align="center">
<img src="public/logo.jpeg" width="60%" style="padding:50px;border-radius:15px;" alt="Innova Logo" /></p>


<p style="font-size:3em;" align="center">
  🚚 RyR Comisiones <br>
   <img src="https://img.shields.io/badge/Laravel-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel" />
  <img src="https://img.shields.io/badge/Docker-2496ED?style=flat-square&logo=docker&logoColor=white" alt="Docker" />
  <img src="https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL" />
</p>

<p align="center">
 
</p>


## 🌟 Características

- 🐳 Dockerizado con PHP, MySQL y phpMyAdmin  
- 🔐 Autenticación con Sanctum  
- 🏗️ Arquitectura hexagonal  
- 🔍 PHPStan para análisis estático  
- ✨ PHP-CS-Fixer para formateo de código  
- ⚙️ GitHub Actions para CI  
- 🔄 Pre-push hooks para verificación de código  
- 🧪 Tests automatizados incluidos  

## 📋 Requisitos

- Docker 🐳  
- Docker Compose 🐙  
- PHP 8.2 🐘  
- Composer 📦  

## 🛠️ Instalación

### 1. Clonar el Proyecto

- Clona el repositorio:

git clone https://github.com/innova-developers/ryr-back.git
cd ryr-back

### 2. ⚙️ Configuración Inicial

- Copia el archivo `.env.example` a `.env`:
cp .env.example .env

- Inicia los contenedores de Docker:
docker-compose up -d --build

- Instala las dependencias de Composer:
composer install

- Genera la clave de aplicación:
php artisan key:generate

- Ejecuta las migraciones:
php artisan migrate

- Ejecuta los tests para verificar que todo funciona:
php artisan test

### 3.🔍 Análisis de Código
#### PHPStan : composer analyse
#### PHP-CS-Fixer composer format

# 📶 EndPoints 
## 🔐 Autenticación

### POST `/api/login`
Inicia sesión y devuelve un token de acceso.

**Parámetros:**
- `email` (string, requerido)
- `password` (string, requerido)

**Respuesta exitosa:**
```json
{
  "success": true,
  "token": "TOKEN_GENERADO",
  "user": {
    "id": 1,
    "name": "Nombre",
    "email": "usuario@ejemplo.com",
    "role": "admin"
  }
}
```

### POST `/api/logout`
Cierra la sesión del usuario autenticado y revoca el token.

**Respuesta exitosa:**
```json
{
  "success": true,
  "message": "Sesión cerrada correctamente"
}
```

## 👤 Usuarios

### POST `/api/users`
Crea un nuevo usuario con los datos proporcionados.

### Payload de ejemplo

```json
{
  "name": "Juan Pérez",
  "email": "nuevo@ejemplo.com",
  "password": "passwordseguro",
  "role": "administrador"
}
```
### Respuesta exitosa

```json
{
  "success": true,
  "message": "Usuario creado correctamente",
  "user": {
      "id": 1,
      "name": "Juan Pérez",
      "email": "nuevo@ejemplo.com",
      "role": "administrador"
    }
}
```

### Respuesta Erronea 

```json
{
  "success": false,
    "message": "Datos inválidos: The name field is required., The email field must be a valid email address., The password field is required., The selected role is invalid."
}
```

### Validaciones

- **name**: requerido, string, máximo 255 caracteres
- **email**: requerido, email válido, único, máximo 255 caracteres
- **password**: requerido, string, mínimo 8 caracteres
- **role**: requerido, string, uno de: `administrador`, `mostrador`,`cadete`,`cliente`

### GET `/api/users`
Devuelve la lista de usuarios registrados (requiere autenticación).

##### Respuesta exitosa
```json
[
  {
    "id": 1,
    "name": "Juan",
    "email": "juan@example.com",
    "role": "admin",
  },
  ...
]
```

### DELETE `/api/users/{id}`
Elimina un usuario por su ID (requiere autenticación y rol de administrador).

#### Parámetros de ruta:
- `id` (integer, requerido): ID del usuario a eliminar.

##### Respuesta exitosa
```json
[
    {
        "id": 1,
        "name": "Juan",
        "email": "juan@example.com",
        "role": "admin",
    },
    ...
]
```
