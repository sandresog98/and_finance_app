<?php
/**
 * AND FINANCE APP - Categoria Model
 * Modelo para gestión de categorías de transacciones
 */

class CategoriaModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener todas las categorías del usuario
     * @param int $userId ID del usuario
     * @param string|null $tipo Filtrar por tipo (ingreso/egreso)
     * @param bool $incluirSistema Si incluir las categorías del sistema (por defecto NO)
     */
    public function getAllByUser(int $userId, ?string $tipo = null, bool $incluirSistema = false): array {
        if ($incluirSistema) {
            $sql = "SELECT * FROM categorias WHERE (usuario_id = :usuario_id OR es_sistema = 1) AND estado = 1";
        } else {
            $sql = "SELECT * FROM categorias WHERE usuario_id = :usuario_id AND estado = 1";
        }
        $params = ['usuario_id' => $userId];
        
        if ($tipo) {
            $sql .= " AND tipo = :tipo";
            $params['tipo'] = $tipo;
        }
        
        $sql .= " ORDER BY tipo, orden, nombre";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener categoría por ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM categorias WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Crear nueva categoría
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO categorias (usuario_id, nombre, tipo, icono, color, es_sistema, orden, estado)
            VALUES (:usuario_id, :nombre, :tipo, :icono, :color, 0, :orden, 1)
        ");
        
        $stmt->execute([
            'usuario_id' => $data['usuario_id'],
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'icono' => $data['icono'] ?? 'bi-tag',
            'color' => $data['color'] ?? '#55A5C8',
            'orden' => $data['orden'] ?? $this->getNextOrden($data['usuario_id'], $data['tipo'])
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Actualizar categoría
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = ['id' => $id];
        
        $allowedFields = ['nombre', 'tipo', 'icono', 'color', 'orden', 'estado'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE categorias SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Eliminar categoría (solo personalizadas)
     */
    public function delete(int $id): bool {
        // No permitir eliminar categorías del sistema
        $categoria = $this->getById($id);
        if (!$categoria || $categoria['es_sistema'] == 1) {
            return false;
        }
        
        // Verificar si tiene transacciones asociadas
        if ($this->tieneTransacciones($id)) {
            // Solo desactivar, no eliminar
            return $this->update($id, ['estado' => 0]);
        }
        
        $stmt = $this->db->prepare("DELETE FROM categorias WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Verificar si la categoría tiene transacciones
     */
    public function tieneTransacciones(int $id): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM transacciones WHERE categoria_id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Obtener siguiente orden disponible
     */
    private function getNextOrden(int $userId, string $tipo): int {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(orden), 0) + 1 FROM categorias 
            WHERE usuario_id = :usuario_id AND es_sistema = 0 AND tipo = :tipo
        ");
        $stmt->execute(['usuario_id' => $userId, 'tipo' => $tipo]);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Obtener iconos disponibles
     */
    public function getIconosDisponibles(): array {
        return [
            'bi-basket' => 'Canasta',
            'bi-car-front' => 'Auto',
            'bi-house' => 'Casa',
            'bi-lightning' => 'Electricidad',
            'bi-heart-pulse' => 'Salud',
            'bi-book' => 'Libro',
            'bi-controller' => 'Gaming',
            'bi-bag' => 'Bolsa',
            'bi-cup-hot' => 'Café',
            'bi-gift' => 'Regalo',
            'bi-laptop' => 'Laptop',
            'bi-phone' => 'Teléfono',
            'bi-credit-card' => 'Tarjeta',
            'bi-wallet2' => 'Billetera',
            'bi-piggy-bank' => 'Ahorro',
            'bi-graph-up-arrow' => 'Inversión',
            'bi-airplane' => 'Viaje',
            'bi-cart' => 'Compras',
            'bi-bicycle' => 'Bicicleta',
            'bi-bus-front' => 'Bus',
            'bi-tag' => 'Etiqueta',
            'bi-star' => 'Estrella',
            'bi-trophy' => 'Trofeo',
            'bi-tools' => 'Herramientas',
            'bi-droplet' => 'Agua',
            'bi-fire' => 'Fuego'
        ];
    }
}

