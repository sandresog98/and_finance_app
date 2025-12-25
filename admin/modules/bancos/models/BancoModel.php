<?php
/**
 * AND FINANCE APP - Banco Model
 * Modelo para gestión de bancos
 */

class BancoModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener todos los bancos
     */
    public function getAll(bool $soloActivos = false): array {
        $sql = "SELECT * FROM bancos";
        if ($soloActivos) {
            $sql .= " WHERE estado = 1";
        }
        $sql .= " ORDER BY orden ASC, nombre ASC";
        
        return $this->db->query($sql)->fetchAll();
    }
    
    /**
     * Obtener banco por ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM bancos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $banco = $stmt->fetch();
        
        return $banco ?: null;
    }
    
    /**
     * Crear nuevo banco
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO bancos (nombre, codigo, logo, color_primario, estado, orden)
            VALUES (:nombre, :codigo, :logo, :color_primario, :estado, :orden)
        ");
        
        $stmt->execute([
            'nombre' => $data['nombre'],
            'codigo' => $data['codigo'] ?? null,
            'logo' => $data['logo'] ?? null,
            'color_primario' => $data['color_primario'] ?? null,
            'estado' => $data['estado'] ?? 1,
            'orden' => $data['orden'] ?? 0
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Actualizar banco
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = ['id' => $id];
        
        $allowedFields = ['nombre', 'codigo', 'logo', 'color_primario', 'estado', 'orden'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE bancos SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Eliminar banco
     */
    public function delete(int $id): bool {
        // Verificar si hay cuentas asociadas
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM cuentas WHERE banco_id = :id");
        $stmt->execute(['id' => $id]);
        
        if ($stmt->fetchColumn() > 0) {
            return false; // No se puede eliminar si tiene cuentas asociadas
        }
        
        $stmt = $this->db->prepare("DELETE FROM bancos WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Cambiar estado del banco
     */
    public function toggleEstado(int $id): bool {
        $stmt = $this->db->prepare("
            UPDATE bancos SET estado = IF(estado = 1, 0, 1) WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Verificar si existe un banco con el mismo nombre
     */
    public function existeNombre(string $nombre, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM bancos WHERE nombre = :nombre";
        $params = ['nombre' => $nombre];
        
        if ($excludeId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Obtener el siguiente orden disponible
     */
    public function getNextOrden(): int {
        $result = $this->db->query("SELECT MAX(orden) FROM bancos")->fetchColumn();
        return ($result ?? 0) + 1;
    }
    
    /**
     * Contar bancos
     */
    public function count(bool $soloActivos = false): int {
        $sql = "SELECT COUNT(*) FROM bancos";
        if ($soloActivos) {
            $sql .= " WHERE estado = 1";
        }
        return (int) $this->db->query($sql)->fetchColumn();
    }
}

