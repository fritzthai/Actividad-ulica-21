<?php
declare(strict_types=1);
require __DIR__ . '/conexion.php';
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responder(int $estado, array $datos): void
{
    http_response_code($estado);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $metodo = $_SERVER['REQUEST_METHOD'];
    if ($metodo === 'GET') {
        $_SESSION['token'] = $_SESSION['token'] ?? bin2hex(random_bytes(32));
        $consulta = conectar()->prepare(
            'SELECT id, nombre, categoria, precio, stock FROM productos ORDER BY id'
        );
        $consulta->execute();
        responder(200, ['productos' => $consulta->fetchAll(), 'token' => $_SESSION['token']]);
    }
    if ($metodo !== 'POST') {
        header('Allow: GET, POST');
        responder(405, ['error' => 'Método no permitido.']);
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['token']) || !hash_equals($_SESSION['token'], $token)) {
        responder(403, ['error' => 'Recargá la página e intentá nuevamente.']);
    }
    $datos = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($datos)) {
        responder(400, ['error' => 'El cuerpo debe ser un objeto JSON.']);
    }
    $nombre = $datos['nombre'] ?? null;
    $categoria = $datos['categoria'] ?? null;
    $precio = $datos['precio'] ?? null;
    $stock = $datos['stock'] ?? null;
    if (!is_string($nombre) || !is_string($categoria) ||
        !is_string($precio) || !is_string($stock)) {
        responder(422, ['error' => 'Completá todos los campos con valores válidos.']);
    }
    $nombre = trim($nombre);
    $categoria = trim($categoria);
    $precio = trim($precio);
    $stock = trim($stock);
    if ($nombre === '' || $categoria === '' ||
        mb_strlen($nombre, 'UTF-8') > 100 || mb_strlen($categoria, 'UTF-8') > 50) {
        responder(422, ['error' => 'Nombre obligatorio de hasta 100 caracteres y categoría de hasta 50.']);
    }
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $precio)) {
        responder(422, ['error' => 'Precio no negativo, de hasta 8 dígitos enteros y 2 decimales.']);
    }
    if (!preg_match('/^\d{1,10}$/D', $stock) || (float) $stock > 2147483647) {
        responder(422, ['error' => 'Stock entero entre 0 y 2147483647.']);
    }
    $pdo = conectar();
    $consulta = $pdo->prepare(
        'INSERT INTO productos (nombre, categoria, precio, stock)
         VALUES (:nombre, :categoria, :precio, :stock)'
    );
    $consulta->execute([
        'nombre' => $nombre,
        'categoria' => $categoria,
        'precio' => $precio,
        'stock' => (int) $stock,
    ]);
    responder(201, ['mensaje' => 'Producto guardado.']);
} catch (JsonException $e) {
    responder(400, ['error' => 'JSON inválido.']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    responder(500, ['error' => 'No se pudo completar la operación. Revisá la configuración del servidor.']);
}
