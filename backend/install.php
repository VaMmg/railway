<?php
// Instalador simple para Railway
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Instalador - Sistema de Créditos</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
    <h1>🚀 Instalador del Sistema de Créditos</h1>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<h2>Instalando base de datos...</h2>";
        
        require_once __DIR__ . '/config/conexion.php';
        
        try {
            $pdo = getPDO();
            echo "<p class='success'>✓ Conectado a la base de datos</p>";
            
            // SQL simplificado
            $sql = "
            CREATE TABLE IF NOT EXISTS usuarios (
                id_usuario INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                nombre VARCHAR(100) NOT NULL,
                apellido VARCHAR(100),
                email VARCHAR(100),
                rol ENUM('admin', 'gerente', 'cajero') DEFAULT 'cajero',
                activo BOOLEAN DEFAULT TRUE,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS personas (
                id_persona INT AUTO_INCREMENT PRIMARY KEY,
                tipo_documento ENUM('DNI', 'CE', 'RUC', 'PASAPORTE') DEFAULT 'DNI',
                numero_documento VARCHAR(20) UNIQUE NOT NULL,
                nombres VARCHAR(100) NOT NULL,
                apellido_paterno VARCHAR(100),
                apellido_materno VARCHAR(100),
                fecha_nacimiento DATE,
                telefono VARCHAR(20),
                email VARCHAR(100),
                direccion TEXT,
                activo BOOLEAN DEFAULT TRUE,
                fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS clientes (
                id_cliente INT AUTO_INCREMENT PRIMARY KEY,
                id_persona INT NOT NULL,
                codigo_cliente VARCHAR(20) UNIQUE,
                calificacion ENUM('A', 'B', 'C', 'D') DEFAULT 'C',
                limite_credito DECIMAL(10,2) DEFAULT 0,
                observaciones TEXT,
                activo BOOLEAN DEFAULT TRUE,
                fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_persona) REFERENCES personas(id_persona)
            );
            
            CREATE TABLE IF NOT EXISTS creditos (
                id_credito INT AUTO_INCREMENT PRIMARY KEY,
                id_cliente INT NOT NULL,
                monto_total DECIMAL(10,2) NOT NULL,
                tasa_interes DECIMAL(5,2) DEFAULT 0,
                numero_cuotas INT NOT NULL,
                monto_cuota DECIMAL(10,2) NOT NULL,
                fecha_desembolso DATE NOT NULL,
                estado ENUM('pendiente', 'aprobado', 'rechazado', 'activo', 'pagado', 'vencido') DEFAULT 'pendiente',
                observaciones TEXT,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente)
            );
            ";
            
            $pdo->exec($sql);
            echo "<p class='success'>✓ Tablas creadas correctamente</p>";
            
            // Insertar usuario admin
            $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->exec("INSERT IGNORE INTO usuarios (username, password, nombre, apellido, email, rol) 
                       VALUES ('admin', '$passwordHash', 'Administrador', 'Sistema', 'admin@sistema.com', 'admin')");
            
            echo "<p class='success'>✓ Usuario administrador creado</p>";
            echo "<div class='info'>";
            echo "<h3>Credenciales de acceso:</h3>";
            echo "<pre>";
            echo "Usuario: admin\n";
            echo "Contraseña: admin123\n";
            echo "</pre>";
            echo "</div>";
            
            echo "<p class='success'><strong>¡Instalación completada exitosamente!</strong></p>";
            echo "<p>Ahora puedes <a href='https://triumphant-laughter-production-up.railway.app'>iniciar sesión en tu aplicación</a></p>";
            
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        ?>
        <p>Este instalador creará las tablas básicas y un usuario administrador para tu sistema.</p>
        <p><strong>Nota:</strong> Solo ejecuta esto una vez. Si ya instalaste, no es necesario volver a ejecutar.</p>
        
        <form method="POST">
            <button type="submit">Instalar Base de Datos</button>
        </form>
        <?php
    }
    ?>
</body>
</html>
