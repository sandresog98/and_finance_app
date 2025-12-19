<?php
/**
 * Modelo de Categoría
 */

namespace UI\Modules\Categorias\Models;

use PDO;
use PDOException;

class Category {
    private PDO $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    /**
     * Obtener todas las categorías de un usuario (solo las del usuario, no las predeterminadas del sistema)
     */
    public function getAllByUser(int $userId): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, nombre, tipo, icono, color, es_predeterminada, usuario_id, estado_activo, fecha_creacion
                FROM categorias_categorias
                WHERE usuario_id = ? AND estado_activo = TRUE
                ORDER BY tipo ASC, nombre ASC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Category::getAllByUser error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener categorías por tipo
     */
    public function getByType(int $userId, string $tipo): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, nombre, tipo, icono, color, es_predeterminada
                FROM categorias_categorias
                WHERE usuario_id = ? 
                  AND tipo = ? 
                  AND estado_activo = TRUE
                ORDER BY nombre ASC
            ");
            $stmt->execute([$userId, $tipo]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Category::getByType error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener categoría por ID
     */
    public function getById(int $id, int $userId): ?array {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, nombre, tipo, icono, color, es_predeterminada, usuario_id, estado_activo
                FROM categorias_categorias
                WHERE id = ? AND usuario_id = ? AND estado_activo = TRUE
                LIMIT 1
            ");
            $stmt->execute([$id, $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Category::getById error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Crear nueva categoría
     */
    public function create(array $data): array {
        try {
            // Asignar color automáticamente según el tipo si no se proporciona
            $color = $data['color'] ?? null;
            if (empty($color)) {
                $color = ($data['tipo'] === 'ingreso') ? '#39843A' : '#F1B10B';
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO categorias_categorias (usuario_id, nombre, tipo, icono, color, es_predeterminada, estado_activo)
                VALUES (?, ?, ?, ?, ?, FALSE, TRUE)
            ");
            
            $stmt->execute([
                $data['usuario_id'],
                $data['nombre'],
                $data['tipo'],
                $data['icono'] ?? null,
                $color
            ]);
            
            $id = $this->conn->lastInsertId();
            return ['success' => true, 'id' => $id];
            
        } catch (PDOException $e) {
            error_log('Category::create error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al crear la categoría'];
        }
    }
    
    /**
     * Actualizar categoría (todas las del usuario, incluyendo copias de predeterminadas)
     */
    public function update(int $id, int $userId, array $data): array {
        try {
            // Verificar que la categoría pertenece al usuario
            $category = $this->getById($id, $userId);
            if (!$category) {
                return ['success' => false, 'message' => 'Categoría no encontrada'];
            }
            
            $fields = [];
            $params = [];
            
            if (isset($data['nombre'])) {
                $fields[] = "nombre = ?";
                $params[] = $data['nombre'];
            }
            if (isset($data['tipo'])) {
                $fields[] = "tipo = ?";
                $params[] = $data['tipo'];
                // Si cambia el tipo, actualizar el color automáticamente
                $fields[] = "color = ?";
                $params[] = ($data['tipo'] === 'ingreso') ? '#39843A' : '#F1B10B';
            }
            if (isset($data['icono'])) {
                $fields[] = "icono = ?";
                $params[] = $data['icono'];
            }
            // No permitir actualizar el color manualmente, se asigna automáticamente según el tipo
            
            if (empty($fields)) {
                return ['success' => false, 'message' => 'No hay campos para actualizar'];
            }
            
            $params[] = $id;
            $params[] = $userId;
            
            $sql = "UPDATE categorias_categorias SET " . implode(", ", $fields) . " WHERE id = ? AND usuario_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log('Category::update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al actualizar la categoría'];
        }
    }
    
    /**
     * Eliminar categoría (todas las del usuario, incluyendo copias de predeterminadas)
     */
    public function delete(int $id, int $userId): array {
        try {
            // Verificar que la categoría pertenece al usuario
            $category = $this->getById($id, $userId);
            if (!$category) {
                return ['success' => false, 'message' => 'Categoría no encontrada'];
            }
            
            $stmt = $this->conn->prepare("
                UPDATE categorias_categorias 
                SET estado_activo = FALSE 
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->execute([$id, $userId]);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log('Category::delete error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al eliminar la categoría'];
        }
    }
    
    /**
     * Obtener iconos disponibles
     */
    public static function getAvailableIcons(): array {
        return [
            'fa-home' => 'Casa',
            'fa-utensils' => 'Comida',
            'fa-car' => 'Transporte',
            'fa-heartbeat' => 'Salud',
            'fa-graduation-cap' => 'Educación',
            'fa-film' => 'Entretenimiento',
            'fa-tshirt' => 'Ropa',
            'fa-bolt' => 'Servicios',
            'fa-money-bill-wave' => 'Dinero',
            'fa-chart-line' => 'Inversiones',
            'fa-gift' => 'Regalos',
            'fa-wallet' => 'Billetera',
            'fa-shopping-cart' => 'Compras',
            'fa-mobile-alt' => 'Tecnología',
            'fa-book' => 'Libros',
            'fa-dumbbell' => 'Deportes',
            'fa-plane' => 'Viajes',
            'fa-paw' => 'Mascotas',
            'fa-baby' => 'Bebés',
            'fa-tools' => 'Herramientas'
        ];
    }
}
