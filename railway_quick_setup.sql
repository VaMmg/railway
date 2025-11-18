-- Script rápido para Railway - Solo tablas esenciales

-- Tabla de usuarios
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

-- Insertar usuario de prueba
-- Usuario: admin, Contraseña: admin123
INSERT INTO usuarios (username, password, nombre, apellido, email, rol) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'Sistema', 'admin@sistema.com', 'admin');

-- Tabla de personas
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

-- Tabla de clientes
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

-- Tabla de créditos
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

SELECT 'Base de datos configurada correctamente' AS mensaje;
