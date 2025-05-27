
<p align="center">
  <img src="https://innovadevelopers.com/wp-content/uploads/2023/06/cropped-Logo-Full-Color.png" width="33%" style="background-color:white;padding:50px;border-radius:15px;" alt="Innova Logo" />
</p>


<p style="font-size:3em;" align="center">
  🚀 Laravel Stack <br>
   <img src="https://img.shields.io/badge/Laravel-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel" />
  <img src="https://img.shields.io/badge/Docker-2496ED?style=flat-square&logo=docker&logoColor=white" alt="Docker" />
  <img src="https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL" />
</p>

<p align="center">
 
</p>


Plantilla base para proyectos API en Laravel con arquitectura hexagonal y Docker.

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

### 1. Fork del Proyecto

- Haz click en el botón "Fork" en la parte superior derecha de esta página para crear tu propia copia del repositorio.

- Clona tu repositorio forkeado:


git clone https://github.com/innova-developers/laravelstack-innovadevelopers.git
cd laravelstack-innovadevelopers

### 2. ⚙️ Configuración Inicial

- Copia el archivo `.env.example` a `.env`:
cp .env.example .env

- Inicia los contenedores de Docker:
docker-compose up -d --build

- Instala las dependencias de Composer:
docker-compose exec app composer install

- Genera la clave de aplicación:
docker-compose exec app php artisan key:generate

- Ejecuta las migraciones:
docker-compose exec app php artisan migrate

- Ejecuta los tests para verificar que todo funciona:

docker-compose exec app php artisan test

### 3. 🚀 Deploy a Producción

🐳 Docker 

sudo apt update && sudo apt upgrade -y
sudo apt install docker.io docker-compose git -y

- Clona el repositorio:
git clone [https://github.com/innova-developers/laravelstack-innovadevelopers.git](https://github.com/innova-developers/laravelstack-innovadevelopers.git)
cd laravelstack-innovadevelopers

- 📝 Configura el .env para producción:
nano .env

- 📌 Ajusta los valores:
APP_ENV=production
APP_DEBUG=false
DB_HOST=mysql
DB_PASSWORD=your_strong_password

- ▶️ Inicia los servicios:
docker-compose up -d --build

- 🛡️ Configura el proxy inverso (Nginx):
Crea un archivo de configuración para tu dominio:

server {
    listen 80;
    server_name api.tudominio.com;

    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}

### 4.🧪 Ejecutando Tests
- docker-compose exec app php artisan test

### 5.🔍 Análisis de Código
# PHPStan
docker-compose exec app composer analyse

# PHP-CS-Fixer
docker-compose exec app composer format
- 🛑 Deteniendo los Servicios
docker-compose down

- 📌 Estructura del Proyecto
- 📦 laravelstack-innovadevelopers
- - 🏗️ app
- - - 🏛️ Domain            # Capa de dominio
- - - 🚀 Application       # Casos de uso
- - - ⚙️ Infrastructure   # Implementaciones
- -  📊 tests                # Pruebas automatizadas
- 🐳 docker               # Configuración de Docker
- 📝 .github              # GitHub Actions

