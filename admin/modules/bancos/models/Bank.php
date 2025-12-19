<?php
/**
 * Modelo de Banco
 */

namespace Admin\Modules\Bancos\Models;

use PDO;
use PDOException;

class Bank {
    private PDO $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    /**
     * Obtener todos los bancos
     */
    public function getAll(): array {
        try {
            $stmt = $this->conn->query("
                SELECT id, nombre, logo_url, codigo, pais, estado_activo, 
                       fecha_creacion, fecha_actualizacion
                FROM bancos_bancos
                ORDER BY nombre ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Bank::getAll error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener banco por ID
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, nombre, logo_url, codigo, pais, estado_activo, 
                       fecha_creacion, fecha_actualizacion
                FROM bancos_bancos
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Bank::getById error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Crear nuevo banco
     */
    public function create(array $data): array {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO bancos_bancos (nombre, logo_url, codigo, pais, estado_activo)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['nombre'],
                $data['logo_url'] ?? null,
                $data['codigo'] ?? null,
                $data['pais'] ?? 'Colombia',
                $data['estado_activo'] ?? true
            ]);
            
            $id = $this->conn->lastInsertId();
            return ['success' => true, 'id' => $id];
            
        } catch (PDOException $e) {
            error_log('Bank::create error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al crear el banco'];
        }
    }
    
    /**
     * Actualizar banco
     */
    public function update(int $id, array $data): array {
        try {
            $fields = [];
            $params = [];
            
            if (isset($data['nombre'])) {
                $fields[] = "nombre = ?";
                $params[] = $data['nombre'];
            }
            if (isset($data['logo_url'])) {
                $fields[] = "logo_url = ?";
                $params[] = $data['logo_url'];
            }
            if (isset($data['codigo'])) {
                $fields[] = "codigo = ?";
                $params[] = $data['codigo'];
            }
            if (isset($data['pais'])) {
                $fields[] = "pais = ?";
                $params[] = $data['pais'];
            }
            if (isset($data['estado_activo'])) {
                $fields[] = "estado_activo = ?";
                $params[] = $data['estado_activo'] ? 1 : 0;
            }
            
            if (empty($fields)) {
                return ['success' => false, 'message' => 'No hay campos para actualizar'];
            }
            
            $params[] = $id;
            $sql = "UPDATE bancos_bancos SET " . implode(", ", $fields) . " WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log('Bank::update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al actualizar el banco'];
        }
    }
    
    /**
     * Eliminar banco (soft delete)
     */
    public function delete(int $id): array {
        try {
            $stmt = $this->conn->prepare("
                UPDATE bancos_bancos 
                SET estado_activo = FALSE 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log('Bank::delete error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al eliminar el banco'];
        }
    }
}
