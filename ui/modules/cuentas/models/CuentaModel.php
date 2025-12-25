<?php
/**
 * AND FINANCE APP - Cuenta Model
 * Modelo para gestión de cuentas financieras
 */

class CuentaModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener todas las cuentas de un usuario
     */
    public function getAllByUser(int $userId, bool $soloActivas = true): array {
        $sql = "
            SELECT c.*, 
                   COALESCE(b.nombre, c.banco_personalizado) as banco_nombre, 
                   b.logo as banco_logo, 
                   b.color_primario as banco_color
            FROM cuentas c
            LEFT JOIN bancos b ON c.banco_id = b.id
            WHERE c.usuario_id = :usuario_id
        ";
        
        if ($soloActivas) {
            $sql .= " AND c.estado = 1";
        }
        
        $sql .= " ORDER BY c.es_predeterminada DESC, c.nombre ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['usuario_id' => $userId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener cuenta por ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   COALESCE(b.nombre, c.banco_personalizado) as banco_nombre, 
                   b.logo as banco_logo,
                   b.color_primario as banco_color
            FROM cuentas c
            LEFT JOIN bancos b ON c.banco_id = b.id
            WHERE c.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Crear nueva cuenta
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO cuentas (
                usuario_id, banco_id, banco_personalizado, nombre, tipo, saldo_inicial, saldo_actual,
                moneda, color, icono, es_predeterminada, incluir_en_total, estado
            ) VALUES (
                :usuario_id, :banco_id, :banco_personalizado, :nombre, :tipo, :saldo_inicial, :saldo_actual,
                :moneda, :color, :icono, :es_predeterminada, :incluir_en_total, :estado
            )
        ");
        
        $stmt->execute([
            'usuario_id' => $data['usuario_id'],
            'banco_id' => $data['banco_id'] ?: null,
            'banco_personalizado' => $data['banco_personalizado'] ?: null,
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'] ?? 'efectivo',
            'saldo_inicial' => $data['saldo_inicial'] ?? 0,
            'saldo_actual' => $data['saldo_inicial'] ?? 0,
            'moneda' => $data['moneda'] ?? 'COP',
            'color' => $data['color'] ?? '#55A5C8',
            'icono' => $data['icono'] ?? 'bi-wallet2',
            'es_predeterminada' => $data['es_predeterminada'] ?? 0,
            'incluir_en_total' => $data['incluir_en_total'] ?? 1,
            'estado' => 1
        ]);
        
        $cuentaId = (int) $this->db->lastInsertId();
        
        // Si es predeterminada, quitar la marca a las demás
        if ($data['es_predeterminada'] ?? 0) {
            $this->setPredeterminada($cuentaId, $data['usuario_id']);
        }
        
        return $cuentaId;
    }
    
    /**
     * Actualizar cuenta
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = ['id' => $id];
        
        $allowedFields = ['banco_id', 'banco_personalizado', 'nombre', 'tipo', 'color', 'icono', 'es_predeterminada', 'incluir_en_total', 'estado'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE cuentas SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Obtener información para eliminación de cuenta
     */
    public function getInfoParaEliminar(int $id): array {
        $cuenta = $this->getById($id);
        if (!$cuenta) {
            return ['existe' => false];
        }
        
        // Contar transacciones normales (no transferencias)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM transacciones 
            WHERE cuenta_id = :id AND tipo != 'transferencia' AND estado = 1
        ");
        $stmt->execute(['id' => $id]);
        $transaccionesNormales = (int) $stmt->fetchColumn();
        
        // Contar transferencias (origen o destino)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM transacciones 
            WHERE (cuenta_id = :id OR cuenta_destino_id = :id2) AND tipo = 'transferencia' AND estado = 1
        ");
        $stmt->execute(['id' => $id, 'id2' => $id]);
        $transferencias = (int) $stmt->fetchColumn();
        
        // Fecha de creación y antigüedad
        $fechaCreacion = new DateTime($cuenta['fecha_creacion']);
        $hoy = new DateTime();
        $diferencia = $fechaCreacion->diff($hoy);
        
        if ($diferencia->y > 0) {
            $antiguedad = $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '');
            if ($diferencia->m > 0) {
                $antiguedad .= ' y ' . $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '');
            }
        } elseif ($diferencia->m > 0) {
            $antiguedad = $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '');
        } elseif ($diferencia->d > 0) {
            $antiguedad = $diferencia->d . ' día' . ($diferencia->d > 1 ? 's' : '');
        } else {
            $antiguedad = 'Hoy';
        }
        
        return [
            'existe' => true,
            'cuenta' => $cuenta,
            'transacciones_normales' => $transaccionesNormales,
            'transferencias' => $transferencias,
            'total_transacciones' => $transaccionesNormales + $transferencias,
            'antiguedad' => $antiguedad,
            'fecha_creacion' => $fechaCreacion->format('d/m/Y')
        ];
    }
    
    /**
     * Eliminar cuenta y sus transacciones (excepto transferencias)
     */
    public function delete(int $id): array {
        $info = $this->getInfoParaEliminar($id);
        
        if (!$info['existe']) {
            return ['success' => false, 'message' => 'Cuenta no encontrada'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // 1. Eliminar archivos de transacciones asociadas
            $stmt = $this->db->prepare("
                DELETE FROM transaccion_archivos 
                WHERE transaccion_id IN (
                    SELECT id FROM transacciones 
                    WHERE cuenta_id = :id AND tipo != 'transferencia'
                )
            ");
            $stmt->execute(['id' => $id]);
            
            // 2. Eliminar transacciones normales (NO transferencias)
            $stmt = $this->db->prepare("
                DELETE FROM transacciones 
                WHERE cuenta_id = :id AND tipo != 'transferencia'
            ");
            $stmt->execute(['id' => $id]);
            $transaccionesEliminadas = $stmt->rowCount();
            
            // 3. Desasociar transferencias (poner cuenta_id o cuenta_destino_id en NULL)
            // Para transferencias donde esta cuenta es origen
            $stmt = $this->db->prepare("
                UPDATE transacciones SET cuenta_id = NULL 
                WHERE cuenta_id = :id AND tipo = 'transferencia'
            ");
            $stmt->execute(['id' => $id]);
            
            // Para transferencias donde esta cuenta es destino
            $stmt = $this->db->prepare("
                UPDATE transacciones SET cuenta_destino_id = NULL 
                WHERE cuenta_destino_id = :id AND tipo = 'transferencia'
            ");
            $stmt->execute(['id' => $id]);
            
            // 4. Eliminar la cuenta
            $stmt = $this->db->prepare("DELETE FROM cuentas WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            $this->db->commit();
            
            $mensaje = 'Cuenta eliminada correctamente';
            if ($transaccionesEliminadas > 0) {
                $mensaje .= ". Se eliminaron $transaccionesEliminadas transacción(es).";
            }
            if ($info['transferencias'] > 0) {
                $mensaje .= " Las transferencias fueron conservadas.";
            }
            
            return ['success' => true, 'message' => $mensaje];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()];
        }
    }
    
    /**
     * Actualizar saldo de cuenta
     */
    public function actualizarSaldo(int $id, float $nuevoSaldo): bool {
        $stmt = $this->db->prepare("UPDATE cuentas SET saldo_actual = :saldo WHERE id = :id");
        return $stmt->execute(['id' => $id, 'saldo' => $nuevoSaldo]);
    }
    
    /**
     * Ajustar saldo de cuenta (sumar o restar)
     */
    public function ajustarSaldo(int $id, float $cantidad): bool {
        $stmt = $this->db->prepare("UPDATE cuentas SET saldo_actual = saldo_actual + :cantidad WHERE id = :id");
        return $stmt->execute(['id' => $id, 'cantidad' => $cantidad]);
    }
    
    /**
     * Establecer cuenta como predeterminada
     */
    public function setPredeterminada(int $id, int $userId): bool {
        // Quitar predeterminada a todas las cuentas del usuario
        $this->db->prepare("UPDATE cuentas SET es_predeterminada = 0 WHERE usuario_id = :usuario_id")
            ->execute(['usuario_id' => $userId]);
        
        // Marcar la cuenta seleccionada como predeterminada
        $stmt = $this->db->prepare("UPDATE cuentas SET es_predeterminada = 1 WHERE id = :id AND usuario_id = :usuario_id");
        return $stmt->execute(['id' => $id, 'usuario_id' => $userId]);
    }
    
    /**
     * Obtener saldo total de todas las cuentas del usuario
     */
    public function getSaldoTotal(int $userId): float {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(saldo_actual), 0) 
            FROM cuentas 
            WHERE usuario_id = :usuario_id AND estado = 1 AND incluir_en_total = 1
        ");
        $stmt->execute(['usuario_id' => $userId]);
        return (float) $stmt->fetchColumn();
    }
    
    /**
     * Verificar si el usuario tiene transacciones en la cuenta
     */
    public function tieneTransacciones(int $id): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM transacciones WHERE cuenta_id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Obtener tipos de cuenta disponibles
     */
    public function getTiposCuenta(): array {
        return [
            'efectivo' => ['nombre' => 'Efectivo', 'icono' => 'bi-wallet2'],
            'cuenta_ahorro' => ['nombre' => 'Cuenta de Ahorros', 'icono' => 'bi-piggy-bank'],
            'cuenta_corriente' => ['nombre' => 'Cuenta Corriente', 'icono' => 'bi-bank'],
            'tarjeta_credito' => ['nombre' => 'Tarjeta de Crédito', 'icono' => 'bi-credit-card'],
            'inversion' => ['nombre' => 'Inversión', 'icono' => 'bi-graph-up-arrow']
        ];
    }
}

