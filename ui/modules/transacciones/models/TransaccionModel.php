<?php
/**
 * AND FINANCE APP - Transaccion Model
 * Modelo para gestión de transacciones financieras
 */

class TransaccionModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener transacciones de un usuario con filtros
     */
    public function getByUser(int $userId, array $filters = []): array {
        $sql = "
            SELECT t.*, 
                   c.nombre as cuenta_nombre, c.color as cuenta_color, c.icono as cuenta_icono,
                   cat.nombre as categoria_nombre, cat.icono as categoria_icono, cat.color as categoria_color,
                   cd.nombre as cuenta_destino_nombre,
                   (SELECT COUNT(*) FROM transaccion_archivos WHERE transaccion_id = t.id) as num_archivos
            FROM transacciones t
            LEFT JOIN cuentas c ON t.cuenta_id = c.id
            LEFT JOIN categorias cat ON t.categoria_id = cat.id
            LEFT JOIN cuentas cd ON t.cuenta_destino_id = cd.id
            WHERE t.usuario_id = :usuario_id AND t.estado = 1
        ";
        
        $params = ['usuario_id' => $userId];
        
        // Filtro por tipo
        if (!empty($filters['tipo'])) {
            $sql .= " AND t.tipo = :tipo";
            $params['tipo'] = $filters['tipo'];
        }
        
        // Filtro por cuenta
        if (!empty($filters['cuenta_id'])) {
            $sql .= " AND (t.cuenta_id = :cuenta_id OR t.cuenta_destino_id = :cuenta_id2)";
            $params['cuenta_id'] = $filters['cuenta_id'];
            $params['cuenta_id2'] = $filters['cuenta_id'];
        }
        
        // Filtro por categoría
        if (!empty($filters['categoria_id'])) {
            $sql .= " AND t.categoria_id = :categoria_id";
            $params['categoria_id'] = $filters['categoria_id'];
        }
        
        // Filtro por fecha
        if (!empty($filters['fecha_inicio'])) {
            $sql .= " AND t.fecha_transaccion >= :fecha_inicio";
            $params['fecha_inicio'] = $filters['fecha_inicio'];
        }
        
        if (!empty($filters['fecha_fin'])) {
            $sql .= " AND t.fecha_transaccion <= :fecha_fin";
            $params['fecha_fin'] = $filters['fecha_fin'];
        }
        
        // Filtro por estado realizada
        if (isset($filters['realizada'])) {
            $sql .= " AND t.realizada = :realizada";
            $params['realizada'] = $filters['realizada'];
        }
        
        // Filtro por mes actual
        if (!empty($filters['mes_actual'])) {
            $sql .= " AND MONTH(t.fecha_transaccion) = MONTH(CURRENT_DATE()) AND YEAR(t.fecha_transaccion) = YEAR(CURRENT_DATE())";
        }
        
        // Ordenar: programadas primero (por fecha), luego realizadas
        $sql .= " ORDER BY t.realizada ASC, t.fecha_transaccion DESC, t.id DESC";
        
        // Límite
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener transacción por ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   c.nombre as cuenta_nombre,
                   cat.nombre as categoria_nombre,
                   cd.nombre as cuenta_destino_nombre
            FROM transacciones t
            LEFT JOIN cuentas c ON t.cuenta_id = c.id
            LEFT JOIN categorias cat ON t.categoria_id = cat.id
            LEFT JOIN cuentas cd ON t.cuenta_destino_id = cd.id
            WHERE t.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Crear nueva transacción
     */
    public function create(array $data): int {
        $this->db->beginTransaction();
        
        try {
            $realizada = $data['realizada'] ?? 1;
            
            $stmt = $this->db->prepare("
                INSERT INTO transacciones (
                    usuario_id, cuenta_id, categoria_id, subcategoria_id, tipo, monto,
                    descripcion, comentario, fecha_transaccion, hora_transaccion,
                    cuenta_destino_id, es_recurrente, gasto_recurrente_id, realizada, estado
                ) VALUES (
                    :usuario_id, :cuenta_id, :categoria_id, :subcategoria_id, :tipo, :monto,
                    :descripcion, :comentario, :fecha_transaccion, :hora_transaccion,
                    :cuenta_destino_id, :es_recurrente, :gasto_recurrente_id, :realizada, 1
                )
            ");
            
            $stmt->execute([
                'usuario_id' => $data['usuario_id'],
                'cuenta_id' => $data['cuenta_id'],
                'categoria_id' => $data['categoria_id'] ?? null,
                'subcategoria_id' => $data['subcategoria_id'] ?? null,
                'tipo' => $data['tipo'],
                'monto' => abs($data['monto']),
                'descripcion' => $data['descripcion'] ?? null,
                'comentario' => $data['comentario'] ?? null,
                'fecha_transaccion' => $data['fecha_transaccion'],
                'hora_transaccion' => $data['hora_transaccion'] ?? date('H:i:s'),
                'cuenta_destino_id' => $data['cuenta_destino_id'] ?? null,
                'es_recurrente' => $data['es_recurrente'] ?? 0,
                'gasto_recurrente_id' => $data['gasto_recurrente_id'] ?? null,
                'realizada' => $realizada
            ]);
            
            $transaccionId = (int) $this->db->lastInsertId();
            
            // Solo actualizar saldo si la transacción está marcada como realizada
            if ($realizada == 1) {
                $this->actualizarSaldoCuenta($data['cuenta_id'], $data['tipo'], abs($data['monto']));
                
                // Si es transferencia, actualizar cuenta destino
                if ($data['tipo'] === 'transferencia' && !empty($data['cuenta_destino_id'])) {
                    $this->actualizarSaldoCuenta($data['cuenta_destino_id'], 'ingreso', abs($data['monto']));
                }
            }
            
            $this->db->commit();
            return $transaccionId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Actualizar transacción
     */
    public function update(int $id, array $data): bool {
        $transaccion = $this->getById($id);
        if (!$transaccion) {
            return false;
        }
        
        $this->db->beginTransaction();
        
        try {
            // Solo revertir saldo si estaba realizada
            if ($transaccion['realizada'] == 1) {
                $this->revertirSaldoCuenta($transaccion['cuenta_id'], $transaccion['tipo'], $transaccion['monto']);
                
                if ($transaccion['tipo'] === 'transferencia' && $transaccion['cuenta_destino_id']) {
                    $this->revertirSaldoCuenta($transaccion['cuenta_destino_id'], 'ingreso', $transaccion['monto']);
                }
            }
            
            // Actualizar transacción
            $fields = [];
            $params = ['id' => $id];
            
            $allowedFields = ['cuenta_id', 'categoria_id', 'subcategoria_id', 'tipo', 'monto', 
                             'descripcion', 'comentario', 'fecha_transaccion', 'hora_transaccion', 
                             'cuenta_destino_id', 'realizada'];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            
            if (!empty($fields)) {
                $sql = "UPDATE transacciones SET " . implode(', ', $fields) . " WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }
            
            // Aplicar nuevo saldo solo si está realizada
            $nuevaRealizada = $data['realizada'] ?? $transaccion['realizada'];
            
            if ($nuevaRealizada == 1) {
                $cuentaId = $data['cuenta_id'] ?? $transaccion['cuenta_id'];
                $tipo = $data['tipo'] ?? $transaccion['tipo'];
                $monto = $data['monto'] ?? $transaccion['monto'];
                
                $this->actualizarSaldoCuenta($cuentaId, $tipo, abs($monto));
                
                if ($tipo === 'transferencia') {
                    $cuentaDestinoId = $data['cuenta_destino_id'] ?? $transaccion['cuenta_destino_id'];
                    if ($cuentaDestinoId) {
                        $this->actualizarSaldoCuenta($cuentaDestinoId, 'ingreso', abs($monto));
                    }
                }
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Marcar transacción como realizada
     */
    public function marcarRealizada(int $id, bool $realizada = true): bool {
        $transaccion = $this->getById($id);
        if (!$transaccion) {
            return false;
        }
        
        // Si ya está en el estado solicitado, no hacer nada
        if ($transaccion['realizada'] == ($realizada ? 1 : 0)) {
            return true;
        }
        
        return $this->update($id, ['realizada' => $realizada ? 1 : 0]);
    }
    
    /**
     * Crear ajuste de saldo
     * @param int $userId ID del usuario
     * @param int $cuentaId ID de la cuenta
     * @param float $saldoActual Saldo actual de la cuenta
     * @param float $nuevoSaldo Nuevo saldo deseado
     * @param string|null $descripcion Motivo del ajuste
     * @return int ID de la transacción creada
     */
    public function crearAjuste(int $userId, int $cuentaId, float $saldoActual, float $nuevoSaldo, ?string $descripcion = null): int {
        $diferencia = $nuevoSaldo - $saldoActual;
        
        if ($diferencia == 0) {
            throw new Exception('El nuevo saldo es igual al saldo actual');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Signo al inicio: "+" o "-" seguido del motivo
            $signo = $diferencia > 0 ? '+' : '-';
            $textoDescripcion = $signo . ' ' . ($descripcion ?: 'Ajuste de saldo');
            
            $stmt = $this->db->prepare("
                INSERT INTO transacciones (
                    usuario_id, cuenta_id, tipo, monto, descripcion, 
                    fecha_transaccion, hora_transaccion, realizada, estado
                ) VALUES (
                    :usuario_id, :cuenta_id, 'ajuste', :monto, :descripcion,
                    CURRENT_DATE(), CURRENT_TIME(), 1, 1
                )
            ");
            
            $stmt->execute([
                'usuario_id' => $userId,
                'cuenta_id' => $cuentaId,
                'monto' => abs($diferencia),
                'descripcion' => $textoDescripcion
            ]);
            
            $transaccionId = (int) $this->db->lastInsertId();
            
            // Actualizar saldo de la cuenta directamente al nuevo valor
            $stmtCuenta = $this->db->prepare("
                UPDATE cuentas SET saldo_actual = :nuevo_saldo WHERE id = :cuenta_id
            ");
            $stmtCuenta->execute([
                'nuevo_saldo' => $nuevoSaldo,
                'cuenta_id' => $cuentaId
            ]);
            
            $this->db->commit();
            return $transaccionId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Obtener transacciones programadas (pendientes)
     */
    public function getProgramadas(int $userId, int $limite = 10): array {
        return $this->getByUser($userId, [
            'realizada' => 0,
            'limit' => $limite
        ]);
    }
    
    /**
     * Contar transacciones programadas
     */
    public function contarProgramadas(int $userId): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM transacciones 
            WHERE usuario_id = :usuario_id AND estado = 1 AND realizada = 0
        ");
        $stmt->execute(['usuario_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Eliminar transacción (soft delete)
     */
    public function delete(int $id): bool {
        $transaccion = $this->getById($id);
        if (!$transaccion) {
            return false;
        }
        
        $this->db->beginTransaction();
        
        try {
            // Solo revertir saldo si estaba realizada
            if ($transaccion['realizada'] == 1) {
                $this->revertirSaldoCuenta($transaccion['cuenta_id'], $transaccion['tipo'], $transaccion['monto']);
                
                if ($transaccion['tipo'] === 'transferencia' && $transaccion['cuenta_destino_id']) {
                    $this->revertirSaldoCuenta($transaccion['cuenta_destino_id'], 'ingreso', $transaccion['monto']);
                }
            }
            
            // Marcar como eliminada
            $stmt = $this->db->prepare("UPDATE transacciones SET estado = 0 WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Actualizar saldo de cuenta
     */
    private function actualizarSaldoCuenta(int $cuentaId, string $tipo, float $monto): void {
        $operador = ($tipo === 'ingreso') ? '+' : '-';
        
        $stmt = $this->db->prepare("
            UPDATE cuentas SET saldo_actual = saldo_actual $operador :monto WHERE id = :id
        ");
        $stmt->execute(['id' => $cuentaId, 'monto' => $monto]);
    }
    
    /**
     * Revertir saldo de cuenta
     */
    private function revertirSaldoCuenta(int $cuentaId, string $tipo, float $monto): void {
        $operador = ($tipo === 'ingreso') ? '-' : '+';
        
        $stmt = $this->db->prepare("
            UPDATE cuentas SET saldo_actual = saldo_actual $operador :monto WHERE id = :id
        ");
        $stmt->execute(['id' => $cuentaId, 'monto' => $monto]);
    }
    
    /**
     * Obtener totales por período (solo transacciones realizadas)
     */
    public function getTotalesPorPeriodo(int $userId, string $fechaInicio, string $fechaFin): array {
        $stmt = $this->db->prepare("
            SELECT 
                tipo,
                COALESCE(SUM(monto), 0) as total
            FROM transacciones
            WHERE usuario_id = :usuario_id 
            AND estado = 1
            AND realizada = 1
            AND fecha_transaccion BETWEEN :inicio AND :fin
            GROUP BY tipo
        ");
        $stmt->execute([
            'usuario_id' => $userId,
            'inicio' => $fechaInicio,
            'fin' => $fechaFin
        ]);
        
        $resultados = $stmt->fetchAll();
        
        $totales = ['ingreso' => 0, 'egreso' => 0, 'transferencia' => 0];
        foreach ($resultados as $row) {
            $totales[$row['tipo']] = (float) $row['total'];
        }
        
        return $totales;
    }
    
    /**
     * Obtener totales de transacciones programadas
     */
    public function getTotalesProgramadas(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT 
                tipo,
                COALESCE(SUM(monto), 0) as total
            FROM transacciones
            WHERE usuario_id = :usuario_id 
            AND estado = 1
            AND realizada = 0
            GROUP BY tipo
        ");
        $stmt->execute(['usuario_id' => $userId]);
        
        $resultados = $stmt->fetchAll();
        
        $totales = ['ingreso' => 0, 'egreso' => 0];
        foreach ($resultados as $row) {
            $totales[$row['tipo']] = (float) $row['total'];
        }
        
        return $totales;
    }
    
    /**
     * Guardar archivos adjuntos
     */
    public function guardarArchivo(int $transaccionId, array $fileData): int {
        $stmt = $this->db->prepare("
            INSERT INTO transaccion_archivos (
                transaccion_id, nombre_original, nombre_archivo, ruta, tipo_archivo, mime_type, tamano
            ) VALUES (
                :transaccion_id, :nombre_original, :nombre_archivo, :ruta, :tipo_archivo, :mime_type, :tamano
            )
        ");
        
        $stmt->execute([
            'transaccion_id' => $transaccionId,
            'nombre_original' => $fileData['nombre_original'],
            'nombre_archivo' => $fileData['nombre_archivo'],
            'ruta' => $fileData['ruta'],
            'tipo_archivo' => $fileData['tipo_archivo'],
            'mime_type' => $fileData['mime_type'],
            'tamano' => $fileData['tamano']
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Obtener archivos de una transacción
     */
    public function getArchivos(int $transaccionId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM transaccion_archivos WHERE transaccion_id = :id ORDER BY fecha_creacion DESC
        ");
        $stmt->execute(['id' => $transaccionId]);
        return $stmt->fetchAll();
    }
}
