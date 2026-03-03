# 🛠️ Moran Creative - Backend API (MakerLab)

Bienvenido al motor de **Moran Creative**. Esta es una API robusta construida con **Laravel** para gestionar el flujo completo de cotizaciones, producción de impresiones 3D y validación de pagos en el taller MakerLab.

---

## 🚀 Tecnologías Utilizadas

* **Framework:** Laravel 10+
* **Lenguaje:** PHP 8.1+
* **Base de Datos:** MySQL
* **Autenticación:** Laravel Passport / Sanctum (API Auth)
* **Almacenamiento:** Local Storage (para archivos STL y comprobantes)
* **Notificaciones:** Mailgun / SMTP (para avisos de estado)

---

## ✨ Características Principales

### 📦 Gestión de Pedidos (Orders)
* **Recepción Técnica:** Validación de parámetros 3D (dimensiones, relleno, material, color).
* **Flujo de Estados:** Sistema de transiciones seguras: `solicitado` -> `cotizado` -> `aceptado` -> `en_produccion` -> `listo` -> `entregado`.
* **Manejo de Archivos:** Carga y descarga segura de archivos STL.

### 💳 Sistema de Pagos por Transferencia
* **Validación Manual:** Los clientes suben su captura y el administrador debe validarla manualmente para iniciar la impresión.
* **Seguridad:** Los comprobantes se almacenan de forma aislada para evitar confusiones con los diseños de impresión.

### 📧 Notificaciones Automáticas
* Envío automático de correos electrónicos cada vez que cambia el estado del pedido (ej. "Tu pieza ya está en la impresora").

---

## ⚙️ Instalación y Configuración

Sigue estos pasos para levantar el servidor localmente:

1.  **Clonar el repositorio:**
    ```bash
    git clone [https://github.com/ArthurHack4u/moran-creative-backend.git](https://github.com/ArthurHack4u/moran-creative-backend.git)
    cd moran-creative-backend
    ```

2.  **Instalar dependencias:**
    ```bash
    composer install
    ```

3.  **Configurar el entorno:**
    Copia el archivo de ejemplo y configura tu base de datos y servidor de correo:
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

4.  **Migraciones y Seeders:**
    ```bash
    php artisan migrate --seed
    ```

5.  **Vincular almacenamiento:**
    ```bash
    php artisan storage:link
    ```

6.  **Iniciar servidor:**
    ```bash
    php artisan serve
    ```

---

## 🔒 Variables de Entorno (.env)

Asegúrate de configurar las siguientes llaves para que el sistema funcione correctamente:

* `DB_DATABASE`: Nombre de tu base de datos.
* `MAIL_HOST` / `MAIL_PASSWORD`: Para que los correos de notificación no fallen.
* `FILESYSTEM_DISK`: Configurado en `local` para las descargas de archivos.

---

## 👥 Colaboradores

* **Arturo Moran** (@ArthurHack4u) - Lead Backend Developer & MakerLab Founder.
* **Kevin** (@KevinUac) - Frontend Partner & Collaborator.

---

## 📄 Licencia

Este proyecto es de uso privado para **Moran Creative / MakerLab**. Todos los derechos reservados.