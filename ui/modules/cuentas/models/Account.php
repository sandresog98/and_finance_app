<?php
/**
 * Modelo de Cuenta
 */

namespace UI\Modules\Cuentas\Models;

use PDO;
use PDOException;

class Account {
    private PDO $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    /**
     * Obtener todas las cuentas de un usuario
     */
    public function getAllByUser(int $userId): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT c.id, c.nombre, c.banco_id, c.saldo_inicial, c.saldo_actual, 
                       c.tipo, c.estado_activo, c.fecha_creacion,
                       b.nombre AS banco_nombre, b.logo_url AS banco_logo
                FROM cuentas_cuentas c
                LEFT JOIN bancos_bancos b ON c.banco_id = b.id
                WHERE c.usuario_id = ? AND c.estado_activo = TRUE
                ORDER BY c.fecha_creacion DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Account::getAllByUser error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener cuenta por ID
     */
    public function getById(int $id, int $userId): ?array {
        try {
            $stmt = $this->conn->prepare("
                SELECT c.id, c.nombre, c.banco_id, c.saldo_inicial, c.saldo_actual, 
                       c.tipo, c.estado_activo, c.fecha_creacion,
                       b.nombre AS banco_nombre, b.logo_url AS banco_logo
                FROM cuentas_cuentas c
                LEFT JOIN bancos_bancos b ON c.banco_id = b.id
                WHERE c.id = ? AND c.usuario_id = ? AND c.estado_activo = TRUE
                LIMIT 1
            ");
            $stmt->execute([$id, $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Account::getById error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Crear nueva cuenta
     */
    public function create(array $data): array {
        try {
            // Validar que el banco existe si se proporciona
            if (!empty($data['banco_id'])) {
                $stmt = $this->conn->prepare("SELECT id FROM bancos_bancos WHERE id = ? AND estado_activo = TRUE LIMIT 1");
                $stmt->execute([$data['banco_id']]);
                if (!$stmt->fetch()) {
                    return ['success' => false, 'message' => 'Banco no válido'];
                }
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO cuentas_cuentas (usuario_id, nombre, banco_id, saldo_inicial, saldo_actual, tipo, estado_activo)
                VALUES (?, ?, ?, ?, ?, ?, TRUE)
            ");
            
            $saldoInicial = (float)($data['saldo_inicial'] ?? 0);
            
            $stmt->execute([
                $data['usuario_id'],
                $data['nombre'],
                $data['banco_id'] ?? null,
                $saldoInicial,
                $saldoInicial, // saldo_actual inicia igual al saldo_inicial
                $data['tipo'] ?? 'bancaria'
            ]);
            
            $id = $this->conn->lastInsertId();
            return ['success' => true, 'id' => $id];
            
        } catch (PDOException $e) {
            error_log('Account::create error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al crear la cuenta'];
        }
    }
    
    /**
     * Actualizar cuenta
     */
    public function update(int $id, int $userId, array $data): array {
        try {
            // Verificar que la cuenta pertenece al usuario
            $account = $this->getById($id, $userId);
            if (!$account) {
                return ['success' => false, 'message' => 'Cuenta no encontrada'];
            }
            
            // Validar banco si se proporciona
            if (isset($data['banco_id']) && !empty($data['banco_id'])) {
                $stmt = $this->conn->prepare("SELECT id FROM bancos_bancos WHERE id = ? AND estado_activo = TRUE LIMIT 1");
                $stmt->execute([$data['banco_id']]);
                if (!$stmt->fetch()) {
                    return ['success' => false, 'message' => 'Banco no válido'];
                }
            }
            
            $fields = [];
            $params = [];
            
            if (isset($data['nombre'])) {
                $fields[] = "nombre = ?";
                $params[] = $data['nombre'];
            }
            if (isset($data['banco_id'])) {
                $fields[] = "banco_id = ?";
                $params[] = $data['banco_id'] ?: null;
            }
            if (isset($data['tipo'])) {
                $fields[] = "tipo = ?";
                $params[] = $data['tipo'];
            }
            
            if (empty($fields)) {
                return ['success' => false, 'message' => 'No hay campos para actualizar'];
            }
            
            $params[] = $id;
            $params[] = $userId;
            
            $sql = "UPDATE cuentas_cuentas SET " . implode(", ", $fields) . " WHERE id = ? AND usuario_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log('Account::update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al actualizar la cuenta'];
        }
    }
    
    /**
     * Eliminar cuenta (soft delete)
     */
    public function delete(int $id, int $userId): array {
        try {
            // Verificar que la cuenta pertenece al usuario
            $account = $this->getById($id, $userId);
            if (!$account) {
                return ['success' => false, 'message' => 'Cuenta no encontrada'];
            }
            
            $stmt = $this->conn->prepare("
                UPDATE cuentas_cuentas 
                SET estado_activo = FALSE 
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->execute([$id, $userId]);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log('Account::delete error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al eliminar la cuenta'];
        }
    }
    
    /**
     * Actualizar saldo de cuenta
     */
    public function updateBalance(int $id, float $newBalance): bool {
        try {
            $stmt = $this->conn->prepare("
                UPDATE cuentas_cuentas 
                SET saldo_actual = ? 
                WHERE id = ?
            ");
            return $stmt->execute([$newBalance, $id]);
        } catch (PDOException $e) {
            error_log('Account::updateBalance error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Recalcular saldo actual de una cuenta basándose en todas las transacciones ejecutadas
     */
    public function recalculateBalance(int $accountId, int $userId): float {
        try {
            // Obtener saldo inicial
            $stmt = $this->conn->prepare("SELECT saldo_inicial FROM cuentas_cuentas WHERE id = ? AND usuario_id = ? LIMIT 1");
            $stmt->execute([$accountId, $userId]);
            $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cuenta) {
                error_log("Account::recalculateBalance - Cuenta no encontrada: accountId=$accountId, userId=$userId");
                return 0;
            }
            
            $saldo = (float)$cuenta['saldo_inicial'];
            
            // Verificar si la columna es_programada existe
            $stmt = $this->conn->query("SHOW COLUMNS FROM transacciones_transacciones LIKE 'es_programada'");
            $tieneEsProgramada = $stmt->rowCount() > 0;
            
            $fechaActual = date('Y-m-d');
            error_log("Account::recalculateBalance - accountId=$accountId, userId=$userId, fechaActual=$fechaActual, tieneEsProgramada=" . ($tieneEsProgramada ? 'true' : 'false'));
            
            // Verificar si hay ajustes ejecutados
            // Si hay ajustes, el saldo ya fue establecido directamente por createAdjustment
            // y no necesitamos recalcular desde cero, solo confiar en el saldo_actual
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as total
                FROM transacciones_transacciones
                WHERE cuenta_id = ? AND usuario_id = ? AND estado_activo = TRUE 
                AND tipo = 'ajuste'
                AND fecha <= ?
            ");
            $stmt->execute([$accountId, $userId, $fechaActual]);
            $tieneAjustesEjecutados = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
            
            // Verificar si hay ajustes ejecutados
            // Si hay ajustes, el saldo_actual ya fue establecido directamente por createAdjustment
            // y ese saldo ya incluye todas las transacciones hasta el momento del ajuste
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as total
                FROM transacciones_transacciones
                WHERE cuenta_id = ? AND usuario_id = ? AND estado_activo = TRUE 
                AND tipo = 'ajuste'
                AND fecha <= ?
            ");
            $stmt->execute([$accountId, $userId, $fechaActual]);
            $tieneAjustesEjecutados = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
            
            // Obtener el último ajuste ejecutado
            $stmt = $this->conn->prepare("
                SELECT fecha, id
                FROM transacciones_transacciones
                WHERE cuenta_id = ? AND usuario_id = ? AND estado_activo = TRUE 
                AND tipo = 'ajuste'
                AND fecha <= ?
                ORDER BY fecha DESC, id DESC
                LIMIT 1
            ");
            $stmt->execute([$accountId, $userId, $fechaActual]);
            $ultimoAjuste = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ultimoAjuste) {
                // Si hay un ajuste ejecutado, el saldo_actual debería haber sido actualizado por createAdjustment
                // Usar ese saldo directamente y aplicar solo transferencias destino posteriores
                $fechaAjuste = $ultimoAjuste['fecha'];
                $idAjuste = $ultimoAjuste['id'];
                
                // Obtener el saldo_actual de la cuenta (que debería estar actualizado por createAdjustment)
                $stmt = $this->conn->prepare("SELECT saldo_actual FROM cuentas_cuentas WHERE id = ? LIMIT 1");
                $stmt->execute([$accountId]);
                $cuentaActual = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($cuentaActual) {
                    $saldo = (float)$cuentaActual['saldo_actual'];
                    error_log("Account::recalculateBalance - Ajuste ejecutado detectado (fecha: $fechaAjuste, id: $idAjuste), saldo_actual desde BD: $saldo");
                    
                    // Si el saldo es 0 pero hay un ajuste, puede ser que createAdjustment no lo actualizó
                    // En ese caso, intentar extraer el saldo del comentario del ajuste
                    if ($saldo == 0) {
                        $stmt = $this->conn->prepare("SELECT comentario FROM transacciones_transacciones WHERE id = ? LIMIT 1");
                        $stmt->execute([$idAjuste]);
                        $ajusteComentario = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($ajusteComentario && preg_match('/Saldo establecido: \$?([\d.,]+)/', $ajusteComentario['comentario'], $matches)) {
                            $saldoExtraido = (float)str_replace(['.', ','], ['', '.'], $matches[1]);
                            error_log("Account::recalculateBalance - Saldo extraído del comentario: $saldoExtraido");
                            $saldo = $saldoExtraido;
                            // Actualizar el saldo en la BD
                            $this->updateBalance($accountId, $saldo);
                        }
                    }
                    
                    // Obtener transferencias destino que ocurrieron DESPUÉS del último ajuste
                    $stmt = $this->conn->prepare("
                        SELECT monto, fecha" . ($tieneEsProgramada ? ", es_programada" : "") . "
                        FROM transacciones_transacciones
                        WHERE cuenta_destino_id = ? AND usuario_id = ? AND estado_activo = TRUE
                        AND tipo = 'transferencia'
                        AND (fecha > ? OR (fecha = ? AND id > ?))
                        ORDER BY fecha ASC, id ASC
                    ");
                    $stmt->execute([$accountId, $userId, $fechaAjuste, $fechaAjuste, $idAjuste]);
                    $transferenciasPostAjuste = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Filtrar solo las transferencias ejecutadas
                    foreach ($transferenciasPostAjuste as $t) {
                        $fechaTransStr = $t['fecha'];
                        $esEjecutada = false;
                        if ($tieneEsProgramada) {
                            $esProgramada = (bool)($t['es_programada'] ?? false);
                            $fechaPasadaOActual = $fechaTransStr <= $fechaActual;
                            $esEjecutada = !$esProgramada || $fechaPasadaOActual;
                        } else {
                            $esEjecutada = $fechaTransStr <= $fechaActual;
                        }
                        
                        if ($esEjecutada) {
                            $monto = (float)$t['monto'];
                            $saldo += $monto;
                            error_log("  + Transferencia (destino) post-ajuste: $monto -> Saldo: $saldo");
                        }
                    }
                    
                    // Actualizar el saldo final
                    $this->updateBalance($accountId, $saldo);
                    error_log("Account::recalculateBalance - RESUMEN con ajuste: accountId=$accountId, saldoFinal=$saldo");
                    return $saldo;
                }
            }
            
            // Si no hay ajustes, proceder con el cálculo normal desde el saldo inicial
            // Obtener TODAS las transacciones de esta cuenta (sin filtrar por fecha)
            // Luego filtraremos en PHP para considerar solo las ejecutadas
            $stmt = $this->conn->prepare("
                SELECT tipo, monto, cuenta_destino_id, fecha" . ($tieneEsProgramada ? ", es_programada" : "") . "
                FROM transacciones_transacciones
                WHERE cuenta_id = ? AND usuario_id = ? AND estado_activo = TRUE
                AND tipo != 'ajuste'
                ORDER BY fecha ASC, id ASC
            ");
            $stmt->execute([$accountId, $userId]);
            $todasTransacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filtrar solo las transacciones ejecutadas (no programadas o con fecha pasada/actual)
            $transacciones = [];
            foreach ($todasTransacciones as $t) {
                // Comparar fechas como strings (YYYY-MM-DD) para evitar problemas de zona horaria
                $fechaTransStr = $t['fecha'];
                
                $esEjecutada = false;
                if ($tieneEsProgramada) {
                    // Si tiene la columna, usar es_programada
                    // Una transacción está ejecutada si:
                    // 1. NO está marcada como programada, O
                    // 2. Está marcada como programada pero su fecha ya pasó o es hoy
                    $esProgramada = (bool)($t['es_programada'] ?? false);
                    $fechaPasadaOActual = $fechaTransStr <= $fechaActual;
                    $esEjecutada = !$esProgramada || $fechaPasadaOActual;
                } else {
                    // Si no tiene la columna, usar solo la fecha
                    $esEjecutada = $fechaTransStr <= $fechaActual;
                }
                
                if ($esEjecutada) {
                    $transacciones[] = $t;
                }
            }
            
            error_log("Account::recalculateBalance - Total transacciones (sin ajustes): " . count($todasTransacciones) . ", Ejecutadas: " . count($transacciones));
            
            // Procesar transacciones normales (ingreso, egreso, transferencias)
            $saldoAntes = $saldo;
            foreach ($transacciones as $t) {
                $monto = (float)$t['monto'];
                if ($t['tipo'] === 'ingreso') {
                    $saldo += $monto;
                    error_log("  + Ingreso: $monto -> Saldo: $saldo");
                } elseif ($t['tipo'] === 'egreso') {
                    $saldo -= $monto;
                    error_log("  - Egreso: $monto -> Saldo: $saldo");
                } elseif ($t['tipo'] === 'transferencia') {
                    // Para transferencias, restar de la cuenta origen
                    $saldo -= $monto;
                    error_log("  - Transferencia (origen): $monto -> Saldo: $saldo");
                }
            }
            error_log("Account::recalculateBalance - Saldo después de transacciones: $saldo (antes: $saldoAntes)");
            
            // Obtener TODAS las transferencias donde esta cuenta es destino
            $stmt = $this->conn->prepare("
                SELECT monto, fecha" . ($tieneEsProgramada ? ", es_programada" : "") . "
                FROM transacciones_transacciones
                WHERE cuenta_destino_id = ? AND usuario_id = ? AND estado_activo = TRUE
                AND tipo = 'transferencia'
                ORDER BY fecha ASC, id ASC
            ");
            $stmt->execute([$accountId, $userId]);
            $todasTransferenciasDestino = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filtrar solo las transferencias ejecutadas
            $transferenciasDestino = [];
            foreach ($todasTransferenciasDestino as $t) {
                // Comparar fechas como strings (YYYY-MM-DD) para evitar problemas de zona horaria
                $fechaTransStr = $t['fecha'];
                
                $esEjecutada = false;
                if ($tieneEsProgramada) {
                    $esProgramada = (bool)($t['es_programada'] ?? false);
                    $fechaPasadaOActual = $fechaTransStr <= $fechaActual;
                    $esEjecutada = !$esProgramada || $fechaPasadaOActual;
                } else {
                    $esEjecutada = $fechaTransStr <= $fechaActual;
                }
                
                if ($esEjecutada) {
                    $transferenciasDestino[] = $t;
                }
            }
            
            // Sumar transferencias donde esta cuenta es destino
            $saldoAntesTransferencias = $saldo;
            foreach ($transferenciasDestino as $t) {
                $monto = (float)$t['monto'];
                $saldo += $monto;
                error_log("  + Transferencia (destino): $monto -> Saldo: $saldo");
            }
            error_log("Account::recalculateBalance - Saldo después de transferencias destino: $saldo (antes: $saldoAntesTransferencias)");
            
            // Actualizar el saldo en la base de datos
            $this->updateBalance($accountId, $saldo);
            
            error_log("Account::recalculateBalance - RESUMEN: accountId=$accountId, saldoInicial={$cuenta['saldo_inicial']}, saldoFinal=$saldo, transacciones=" . count($transacciones) . ", transferenciasDestino=" . count($transferenciasDestino));
            
            return $saldo;
        } catch (PDOException $e) {
            error_log('Account::recalculateBalance error: ' . $e->getMessage());
            error_log('Account::recalculateBalance trace: ' . $e->getTraceAsString());
            return 0;
        }
    }
    
    /**
     * Recalcular saldos de todas las cuentas de un usuario
     */
    public function recalculateAllBalances(int $userId): void {
        try {
            $cuentas = $this->getAllByUser($userId);
            error_log("Account::recalculateAllBalances - Recalculando saldos para userId=$userId, cuentas=" . count($cuentas));
            foreach ($cuentas as $cuenta) {
                $this->recalculateBalance($cuenta['id'], $userId);
            }
        } catch (PDOException $e) {
            error_log('Account::recalculateAllBalances error: ' . $e->getMessage());
            error_log('Account::recalculateAllBalances trace: ' . $e->getTraceAsString());
        } catch (\Exception $e) {
            error_log('Account::recalculateAllBalances exception: ' . $e->getMessage());
            error_log('Account::recalculateAllBalances trace: ' . $e->getTraceAsString());
        }
    }
    
    /**
     * Obtener todos los bancos activos
     */
    public function getBanks(): array {
        try {
            $stmt = $this->conn->query("
                SELECT id, nombre, logo_url, codigo
                FROM bancos_bancos
                WHERE estado_activo = TRUE
                ORDER BY nombre ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Account::getBanks error: ' . $e->getMessage());
            return [];
        }
    }
}
