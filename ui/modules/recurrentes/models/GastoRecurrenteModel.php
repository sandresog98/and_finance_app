<?php
/**
 * AND FINANCE APP - Gasto Recurrente Model
 * Modelo para gestión de gastos programados
 */

class GastoRecurrenteModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener todos los gastos recurrentes del usuario
     */
    public function getAllByUser(int $userId, bool $soloActivos = true): array {
        $sql = "
            SELECT gr.*, 
                   c.nombre as cuenta_nombre, c.color as cuenta_color, c.icono as cuenta_icono,
                   cat.nombre as categoria_nombre, cat.icono as categoria_icono, cat.color as categoria_color
            FROM gastos_recurrentes gr
            LEFT JOIN cuentas c ON gr.cuenta_id = c.id
            LEFT JOIN categorias cat ON gr.categoria_id = cat.id
            WHERE gr.usuario_id = :usuario_id
        ";
        
        if ($soloActivos) {
            $sql .= " AND gr.estado = 1";
        }
        
        $sql .= " ORDER BY gr.proxima_ejecucion ASC, gr.nombre ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['usuario_id' => $userId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener gasto recurrente por ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT gr.*, 
                   c.nombre as cuenta_nombre, c.icono as cuenta_icono, c.color as cuenta_color,
                   cat.nombre as categoria_nombre, cat.icono as categoria_icono, cat.color as categoria_color
            FROM gastos_recurrentes gr
            LEFT JOIN cuentas c ON gr.cuenta_id = c.id
            LEFT JOIN categorias cat ON gr.categoria_id = cat.id
            WHERE gr.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Crear nuevo gasto recurrente
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO gastos_recurrentes (
                usuario_id, cuenta_id, categoria_id, nombre, monto, tipo,
                frecuencia, dia_ejecucion, dias_ejecucion, fecha_inicio, fecha_fin,
                proxima_ejecucion, notificar, dias_anticipacion, auto_registrar, estado
            ) VALUES (
                :usuario_id, :cuenta_id, :categoria_id, :nombre, :monto, :tipo,
                :frecuencia, :dia_ejecucion, :dias_ejecucion, :fecha_inicio, :fecha_fin,
                :proxima_ejecucion, :notificar, :dias_anticipacion, :auto_registrar, 1
            )
        ");
        
        // Calcular próxima ejecución
        $proximaEjecucion = $this->calcularProximaEjecucion(
            $data['frecuencia'],
            $data['dia_ejecucion'] ?? null,
            $data['dias_ejecucion'] ?? null,
            $data['fecha_inicio']
        );
        
        $stmt->execute([
            'usuario_id' => $data['usuario_id'],
            'cuenta_id' => $data['cuenta_id'],
            'categoria_id' => $data['categoria_id'] ?? null,
            'nombre' => $data['nombre'],
            'monto' => abs($data['monto']),
            'tipo' => $data['tipo'] ?? 'egreso',
            'frecuencia' => $data['frecuencia'],
            'dia_ejecucion' => $data['dia_ejecucion'] ?? null,
            'dias_ejecucion' => $data['dias_ejecucion'] ?? null,
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $data['fecha_fin'] ?? null,
            'proxima_ejecucion' => $proximaEjecucion,
            'notificar' => $data['notificar'] ?? 1,
            'dias_anticipacion' => $data['dias_anticipacion'] ?? 1,
            'auto_registrar' => $data['auto_registrar'] ?? 0
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Actualizar gasto recurrente
     */
    public function update(int $id, array $data): bool {
        $gasto = $this->getById($id);
        if (!$gasto) return false;
        
        $fields = [];
        $params = ['id' => $id];
        
        $allowedFields = ['cuenta_id', 'categoria_id', 'nombre', 'monto', 'tipo',
                         'frecuencia', 'dia_ejecucion', 'dias_ejecucion', 'fecha_inicio', 
                         'fecha_fin', 'notificar', 'dias_anticipacion', 'auto_registrar', 'estado'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        
        // Recalcular próxima ejecución si cambiaron parámetros relevantes
        if (isset($data['frecuencia']) || isset($data['dia_ejecucion']) || isset($data['dias_ejecucion'])) {
            $frecuencia = $data['frecuencia'] ?? $gasto['frecuencia'];
            $diaEjecucion = $data['dia_ejecucion'] ?? $gasto['dia_ejecucion'];
            $diasEjecucion = $data['dias_ejecucion'] ?? $gasto['dias_ejecucion'];
            $fechaInicio = $data['fecha_inicio'] ?? $gasto['fecha_inicio'];
            
            $proximaEjecucion = $this->calcularProximaEjecucion($frecuencia, $diaEjecucion, $diasEjecucion, $fechaInicio);
            $fields[] = "proxima_ejecucion = :proxima_ejecucion";
            $params['proxima_ejecucion'] = $proximaEjecucion;
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE gastos_recurrentes SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Eliminar gasto recurrente
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM gastos_recurrentes WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Pausar/Reanudar gasto recurrente
     */
    public function toggleEstado(int $id): bool {
        $stmt = $this->db->prepare("
            UPDATE gastos_recurrentes SET estado = IF(estado = 1, 0, 1) WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Ajustar día al último disponible del mes si es necesario
     * Ej: día 31 en febrero -> día 28/29
     */
    private function ajustarDiaMes(int $dia, int $mes, int $anio): int {
        $ultimoDia = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        return min($dia, $ultimoDia);
    }
    
    /**
     * Calcular próxima fecha de ejecución
     */
    private function calcularProximaEjecucion(string $frecuencia, ?int $diaEjecucion, ?string $diasEjecucion, string $fechaInicio): string {
        $hoy = new DateTime();
        $inicio = new DateTime($fechaInicio);
        
        // Si la fecha de inicio es futura, usar esa
        if ($inicio > $hoy) {
            return $inicio->format('Y-m-d');
        }
        
        switch ($frecuencia) {
            case 'diario':
                return $hoy->format('Y-m-d');
                
            case 'semanal':
                $diaSemana = $diaEjecucion ?? 1; // Lunes por defecto
                $proximaFecha = clone $hoy;
                $diaActual = (int)$hoy->format('N');
                
                if ($diaActual >= $diaSemana) {
                    $proximaFecha->modify('+' . (7 - $diaActual + $diaSemana) . ' days');
                } else {
                    $proximaFecha->modify('+' . ($diaSemana - $diaActual) . ' days');
                }
                return $proximaFecha->format('Y-m-d');
                
            case 'quincenal':
                $dias = $diasEjecucion ? explode(',', $diasEjecucion) : [15, 30];
                $mesActual = (int)$hoy->format('m');
                $anioActual = (int)$hoy->format('Y');
                $diaActual = (int)$hoy->format('d');
                
                foreach ($dias as $dia) {
                    $dia = (int)trim($dia);
                    $diaAjustado = $this->ajustarDiaMes($dia, $mesActual, $anioActual);
                    if ($diaAjustado > $diaActual) {
                        return sprintf('%04d-%02d-%02d', $anioActual, $mesActual, $diaAjustado);
                    }
                }
                
                // Siguiente mes
                $siguienteMes = $mesActual == 12 ? 1 : $mesActual + 1;
                $siguienteAnio = $mesActual == 12 ? $anioActual + 1 : $anioActual;
                $primerDia = (int)$dias[0];
                return sprintf('%04d-%02d-%02d', $siguienteAnio, $siguienteMes, $this->ajustarDiaMes($primerDia, $siguienteMes, $siguienteAnio));
                
            case 'mensual':
                $diaMes = $diaEjecucion ?? 1;
                return $this->calcularProximaFechaPeriodica($hoy, $diaMes, 1);
                
            case 'bimestral':
                $diaMes = $diaEjecucion ?? 1;
                return $this->calcularProximaFechaPeriodica($hoy, $diaMes, 2);
                
            case 'trimestral':
                $diaMes = $diaEjecucion ?? 1;
                return $this->calcularProximaFechaPeriodica($hoy, $diaMes, 3);
                
            case 'semestral':
                $diaMes = $diaEjecucion ?? 1;
                return $this->calcularProximaFechaPeriodica($hoy, $diaMes, 6);
                
            case 'anual':
                $proximaFecha = clone $inicio;
                while ($proximaFecha <= $hoy) {
                    $proximaFecha->modify('+1 year');
                }
                return $proximaFecha->format('Y-m-d');
                
            default:
                return $hoy->format('Y-m-d');
        }
    }
    
    /**
     * Calcular próxima fecha para frecuencias mensuales/bimestrales/trimestrales/semestrales
     */
    private function calcularProximaFechaPeriodica(DateTime $hoy, int $diaMes, int $mesesIntervalo): string {
        $mesActual = (int)$hoy->format('m');
        $anioActual = (int)$hoy->format('Y');
        $diaActual = (int)$hoy->format('d');
        
        // Ajustar día al mes actual
        $diaAjustado = $this->ajustarDiaMes($diaMes, $mesActual, $anioActual);
        
        // Si aún no ha pasado el día este mes (incluye HOY)
        if ($diaActual <= $diaAjustado) {
            return sprintf('%04d-%02d-%02d', $anioActual, $mesActual, $diaAjustado);
        }
        
        // Calcular siguiente período
        $siguienteMes = $mesActual + $mesesIntervalo;
        $siguienteAnio = $anioActual;
        
        while ($siguienteMes > 12) {
            $siguienteMes -= 12;
            $siguienteAnio++;
        }
        
        $diaAjustado = $this->ajustarDiaMes($diaMes, $siguienteMes, $siguienteAnio);
        return sprintf('%04d-%02d-%02d', $siguienteAnio, $siguienteMes, $diaAjustado);
    }
    
    /**
     * Obtener gastos próximos a vencer
     */
    public function getProximos(int $userId, int $dias = 7): array {
        $stmt = $this->db->prepare("
            SELECT gr.*, 
                   cat.nombre as categoria_nombre, cat.icono as categoria_icono, cat.color as categoria_color,
                   c.nombre as cuenta_nombre, c.icono as cuenta_icono
            FROM gastos_recurrentes gr
            LEFT JOIN categorias cat ON gr.categoria_id = cat.id
            LEFT JOIN cuentas c ON gr.cuenta_id = c.id
            WHERE gr.usuario_id = :usuario_id 
            AND gr.estado = 1
            AND gr.proxima_ejecucion IS NOT NULL
            AND gr.proxima_ejecucion <= DATE_ADD(CURRENT_DATE(), INTERVAL :dias DAY)
            ORDER BY gr.proxima_ejecucion ASC
        ");
        $stmt->execute(['usuario_id' => $userId, 'dias' => $dias]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener proyección de gastos para un mes
     */
    public function getProyeccionMes(int $userId, int $mes, int $anio): array {
        $gastosRecurrentes = $this->getAllByUser($userId);
        $proyeccion = [];
        
        $primerDia = new DateTime("$anio-$mes-01");
        $ultimoDia = new DateTime($primerDia->format('Y-m-t'));
        
        foreach ($gastosRecurrentes as $gasto) {
            $fechasEjecucion = $this->obtenerFechasEnRango($gasto, $primerDia, $ultimoDia);
            
            foreach ($fechasEjecucion as $fecha) {
                $proyeccion[] = [
                    'fecha' => $fecha,
                    'nombre' => $gasto['nombre'],
                    'monto' => $gasto['monto'],
                    'tipo' => $gasto['tipo'],
                    'categoria_nombre' => $gasto['categoria_nombre'],
                    'categoria_icono' => $gasto['categoria_icono'],
                    'categoria_color' => $gasto['categoria_color']
                ];
            }
        }
        
        // Ordenar por fecha
        usort($proyeccion, fn($a, $b) => strtotime($a['fecha']) - strtotime($b['fecha']));
        
        return $proyeccion;
    }
    
    /**
     * Obtener fechas de ejecución dentro de un rango
     */
    private function obtenerFechasEnRango(array $gasto, DateTime $inicio, DateTime $fin): array {
        $fechas = [];
        $frecuencia = $gasto['frecuencia'];
        $diaEjecucion = $gasto['dia_ejecucion'];
        $diasEjecucion = $gasto['dias_ejecucion'] ? explode(',', $gasto['dias_ejecucion']) : [];
        $mes = (int)$inicio->format('m');
        $anio = (int)$inicio->format('Y');
        
        switch ($frecuencia) {
            case 'mensual':
            case 'bimestral':
            case 'trimestral':
            case 'semestral':
                $dia = $diaEjecucion ?? 1;
                $diaReal = $this->ajustarDiaMes($dia, $mes, $anio);
                $fechas[] = sprintf('%04d-%02d-%02d', $anio, $mes, $diaReal);
                break;
                
            case 'quincenal':
                $dias = !empty($diasEjecucion) ? $diasEjecucion : [15, 30];
                foreach ($dias as $dia) {
                    $dia = (int)trim($dia);
                    $diaReal = $this->ajustarDiaMes($dia, $mes, $anio);
                    $fechas[] = sprintf('%04d-%02d-%02d', $anio, $mes, $diaReal);
                }
                break;
        }
        
        return $fechas;
    }
    
    /**
     * Obtener frecuencias disponibles
     */
    /**
     * Obtener frecuencias disponibles para crear/editar gastos recurrentes
     * Por ahora solo se permite frecuencia mensual
     */
    public function getFrecuencias(): array {
        return [
            'mensual' => ['nombre' => 'Mensual', 'icono' => 'bi-calendar-month', 'descripcion' => 'Una vez al mes'],
        ];
    }
    
    /**
     * Obtener TODAS las frecuencias (para visualización de gastos existentes)
     * Incluye frecuencias deshabilitadas para que los gastos creados anteriormente se muestren correctamente
     */
    public function getTodasFrecuencias(): array {
        return [
            'diario' => ['nombre' => 'Diario', 'icono' => 'bi-calendar-day', 'descripcion' => 'Todos los días'],
            'semanal' => ['nombre' => 'Semanal', 'icono' => 'bi-calendar-week', 'descripcion' => 'Una vez por semana'],
            'quincenal' => ['nombre' => 'Quincenal', 'icono' => 'bi-calendar2-week', 'descripcion' => 'Cada 15 días'],
            'mensual' => ['nombre' => 'Mensual', 'icono' => 'bi-calendar-month', 'descripcion' => 'Una vez al mes'],
            'bimestral' => ['nombre' => 'Bimestral', 'icono' => 'bi-calendar2-range', 'descripcion' => 'Cada 2 meses'],
            'trimestral' => ['nombre' => 'Trimestral', 'icono' => 'bi-calendar3', 'descripcion' => 'Cada 3 meses'],
            'semestral' => ['nombre' => 'Semestral', 'icono' => 'bi-calendar3-range', 'descripcion' => 'Cada 6 meses'],
            'anual' => ['nombre' => 'Anual', 'icono' => 'bi-calendar-event', 'descripcion' => 'Una vez al año']
        ];
    }
    
    /**
     * Obtener días de la semana
     */
    public function getDiasSemana(): array {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo'
        ];
    }
    
    /**
     * Procesar gastos recurrentes pendientes y crear transacciones programadas
     * @param int $userId ID del usuario
     * @return array Resultado del procesamiento
     */
    public function procesarPendientes(int $userId): array {
        $hoy = date('Y-m-d');
        $creadas = 0;
        $errores = [];
        
        // Obtener gastos recurrentes activos con próxima ejecución <= hoy
        $stmt = $this->db->prepare("
            SELECT * FROM gastos_recurrentes 
            WHERE usuario_id = :usuario_id 
            AND estado = 1 
            AND proxima_ejecucion <= :hoy
            AND (fecha_fin IS NULL OR fecha_fin >= :hoy2)
        ");
        $stmt->execute([
            'usuario_id' => $userId,
            'hoy' => $hoy,
            'hoy2' => $hoy
        ]);
        $gastosVencidos = $stmt->fetchAll();
        
        foreach ($gastosVencidos as $gasto) {
            try {
                // Verificar si ya existe una transacción para este gasto en la fecha de ejecución
                $stmtCheck = $this->db->prepare("
                    SELECT COUNT(*) FROM transacciones 
                    WHERE gasto_recurrente_id = :gasto_id 
                    AND fecha_transaccion = :fecha
                    AND estado = 1
                ");
                $stmtCheck->execute([
                    'gasto_id' => $gasto['id'],
                    'fecha' => $gasto['proxima_ejecucion']
                ]);
                
                if ($stmtCheck->fetchColumn() > 0) {
                    // Ya existe, solo actualizar próxima ejecución
                    $this->actualizarProximaEjecucion($gasto['id']);
                    continue;
                }
                
                // Crear transacción programada (realizada = 0)
                $stmtTrans = $this->db->prepare("
                    INSERT INTO transacciones (
                        usuario_id, cuenta_id, categoria_id, subcategoria_id, tipo, monto,
                        descripcion, fecha_transaccion, es_recurrente, gasto_recurrente_id,
                        realizada, estado
                    ) VALUES (
                        :usuario_id, :cuenta_id, :categoria_id, :subcategoria_id, :tipo, :monto,
                        :descripcion, :fecha_transaccion, 1, :gasto_recurrente_id,
                        0, 1
                    )
                ");
                
                $stmtTrans->execute([
                    'usuario_id' => $userId,
                    'cuenta_id' => $gasto['cuenta_id'],
                    'categoria_id' => $gasto['categoria_id'],
                    'subcategoria_id' => $gasto['subcategoria_id'],
                    'tipo' => $gasto['tipo'],
                    'monto' => $gasto['monto'],
                    'descripcion' => $gasto['nombre'],
                    'fecha_transaccion' => $gasto['proxima_ejecucion'],
                    'gasto_recurrente_id' => $gasto['id']
                ]);
                
                // Actualizar próxima ejecución
                $this->actualizarProximaEjecucion($gasto['id']);
                
                // Actualizar última ejecución
                $stmtUpdate = $this->db->prepare("
                    UPDATE gastos_recurrentes 
                    SET ultima_ejecucion = :fecha 
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    'fecha' => $gasto['proxima_ejecucion'],
                    'id' => $gasto['id']
                ]);
                
                $creadas++;
                
            } catch (Exception $e) {
                $errores[] = "Error en '{$gasto['nombre']}': " . $e->getMessage();
            }
        }
        
        return [
            'procesados' => count($gastosVencidos),
            'creadas' => $creadas,
            'errores' => $errores
        ];
    }
    
    /**
     * Actualizar la próxima fecha de ejecución de un gasto recurrente
     * Calcula la siguiente fecha del PRÓXIMO período (no hoy)
     */
    private function actualizarProximaEjecucion(int $gastoId): void {
        $gasto = $this->getById($gastoId);
        if (!$gasto) return;
        
        $nuevaFecha = $this->calcularSiguientePeriodo(
            $gasto['frecuencia'],
            $gasto['dia_ejecucion'],
            $gasto['dias_ejecucion'],
            $gasto['proxima_ejecucion'] ?? date('Y-m-d')
        );
        
        $stmt = $this->db->prepare("
            UPDATE gastos_recurrentes SET proxima_ejecucion = :fecha WHERE id = :id
        ");
        $stmt->execute(['fecha' => $nuevaFecha, 'id' => $gastoId]);
    }
    
    /**
     * Calcular el SIGUIENTE período después de una fecha dada
     * Usado después de pagar para avanzar al próximo ciclo
     */
    private function calcularSiguientePeriodo(string $frecuencia, ?int $diaEjecucion, ?string $diasEjecucion, string $fechaActual): string {
        $fecha = new DateTime($fechaActual);
        
        switch ($frecuencia) {
            case 'diario':
                $fecha->modify('+1 day');
                return $fecha->format('Y-m-d');
                
            case 'semanal':
                $fecha->modify('+1 week');
                return $fecha->format('Y-m-d');
                
            case 'quincenal':
                // Buscar el siguiente día en la lista
                $dias = $diasEjecucion ? array_map('intval', explode(',', $diasEjecucion)) : [15, 30];
                sort($dias);
                $mesActual = (int)$fecha->format('m');
                $anioActual = (int)$fecha->format('Y');
                $diaActual = (int)$fecha->format('d');
                
                // Buscar el siguiente día después del actual
                foreach ($dias as $dia) {
                    $diaAjustado = $this->ajustarDiaMes($dia, $mesActual, $anioActual);
                    if ($diaAjustado > $diaActual) {
                        return sprintf('%04d-%02d-%02d', $anioActual, $mesActual, $diaAjustado);
                    }
                }
                
                // Siguiente mes, primer día de la lista
                $siguienteMes = $mesActual == 12 ? 1 : $mesActual + 1;
                $siguienteAnio = $mesActual == 12 ? $anioActual + 1 : $anioActual;
                $primerDia = $this->ajustarDiaMes($dias[0], $siguienteMes, $siguienteAnio);
                return sprintf('%04d-%02d-%02d', $siguienteAnio, $siguienteMes, $primerDia);
                
            case 'mensual':
                return $this->avanzarMeses($fecha, $diaEjecucion ?? (int)$fecha->format('d'), 1);
                
            case 'bimestral':
                return $this->avanzarMeses($fecha, $diaEjecucion ?? (int)$fecha->format('d'), 2);
                
            case 'trimestral':
                return $this->avanzarMeses($fecha, $diaEjecucion ?? (int)$fecha->format('d'), 3);
                
            case 'semestral':
                return $this->avanzarMeses($fecha, $diaEjecucion ?? (int)$fecha->format('d'), 6);
                
            case 'anual':
                $fecha->modify('+1 year');
                return $fecha->format('Y-m-d');
                
            default:
                $fecha->modify('+1 month');
                return $fecha->format('Y-m-d');
        }
    }
    
    /**
     * Avanzar N meses manteniendo el día de ejecución
     */
    private function avanzarMeses(DateTime $fecha, int $diaEjecucion, int $meses): string {
        $mesActual = (int)$fecha->format('m');
        $anioActual = (int)$fecha->format('Y');
        
        $siguienteMes = $mesActual + $meses;
        $siguienteAnio = $anioActual;
        
        while ($siguienteMes > 12) {
            $siguienteMes -= 12;
            $siguienteAnio++;
        }
        
        $diaAjustado = $this->ajustarDiaMes($diaEjecucion, $siguienteMes, $siguienteAnio);
        return sprintf('%04d-%02d-%02d', $siguienteAnio, $siguienteMes, $diaAjustado);
    }
    
    /**
     * Recalcular próxima ejecución para todos los gastos del usuario
     * Solo recalcula si la próxima ejecución es anterior a hoy Y no se ha ejecutado hoy
     */
    public function recalcularProximasEjecuciones(int $userId): int {
        $hoy = date('Y-m-d');
        $gastos = $this->getAllByUser($userId, true);
        $actualizados = 0;
        
        foreach ($gastos as $gasto) {
            // Si ya se ejecutó hoy, no recalcular (evita sobrescribir después de un pago)
            if ($gasto['ultima_ejecucion'] === $hoy) {
                continue;
            }
            
            // Solo recalcular si la próxima ejecución ya pasó o no está configurada
            if ($gasto['proxima_ejecucion'] === null || $gasto['proxima_ejecucion'] < $hoy) {
                $nuevaFecha = $this->calcularProximaEjecucion(
                    $gasto['frecuencia'],
                    $gasto['dia_ejecucion'],
                    $gasto['dias_ejecucion'],
                    $gasto['fecha_inicio']
                );
                
                if ($nuevaFecha !== $gasto['proxima_ejecucion']) {
                    $stmt = $this->db->prepare("
                        UPDATE gastos_recurrentes SET proxima_ejecucion = :fecha WHERE id = :id
                    ");
                    $stmt->execute(['fecha' => $nuevaFecha, 'id' => $gasto['id']]);
                    $actualizados++;
                }
            }
        }
        
        return $actualizados;
    }
    
    /**
     * Verificar si existe una transacción reciente para el gasto recurrente
     * @param int $gastoId ID del gasto recurrente
     * @return array|null Información de la transacción existente o null
     */
    public function verificarTransaccionExistente(int $gastoId): ?array {
        $stmt = $this->db->prepare("
            SELECT t.id, t.fecha_transaccion, t.realizada, t.monto
            FROM transacciones t
            WHERE t.gasto_recurrente_id = :gasto_id 
            AND t.estado = 1
            AND t.fecha_transaccion >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
            ORDER BY t.fecha_transaccion DESC
            LIMIT 1
        ");
        $stmt->execute(['gasto_id' => $gastoId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Registrar manualmente un pago de gasto recurrente
     * @param int $gastoId ID del gasto recurrente
     * @param bool $realizada Si la transacción debe marcarse como realizada
     * @param bool $forzar Si true, crea nueva transacción aunque exista una reciente
     * @return int ID de la transacción creada o actualizada
     */
    public function registrarPagoManual(int $gastoId, bool $realizada = true, bool $forzar = false): int {
        $gasto = $this->getById($gastoId);
        if (!$gasto) {
            throw new Exception('Gasto recurrente no encontrado');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Buscar si ya existe una transacción programada (no realizada) para este gasto
            $stmtBuscar = $this->db->prepare("
                SELECT id, realizada FROM transacciones 
                WHERE gasto_recurrente_id = :gasto_id 
                AND estado = 1
                AND realizada = 0
                ORDER BY fecha_transaccion DESC
                LIMIT 1
            ");
            $stmtBuscar->execute(['gasto_id' => $gastoId]);
            $transaccionExistente = $stmtBuscar->fetch();
            
            if ($transaccionExistente && !$forzar) {
                // Ya existe una transacción programada, actualizarla
                $transaccionId = (int) $transaccionExistente['id'];
                
                if ($realizada) {
                    // Marcar como realizada
                    $stmtActualizar = $this->db->prepare("
                        UPDATE transacciones 
                        SET realizada = 1, 
                            fecha_transaccion = CURRENT_DATE(),
                            hora_transaccion = CURRENT_TIME()
                        WHERE id = :id
                    ");
                    $stmtActualizar->execute(['id' => $transaccionId]);
                    
                    // Actualizar saldo
                    $signo = $gasto['tipo'] === 'ingreso' ? 1 : -1;
                    $stmtSaldo = $this->db->prepare("
                        UPDATE cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :cuenta_id
                    ");
                    $stmtSaldo->execute([
                        'monto' => $signo * $gasto['monto'],
                        'cuenta_id' => $gasto['cuenta_id']
                    ]);
                }
                // Si no está realizada, la transacción ya existe como programada, no hacer nada más
                
            } else {
                // No existe, crear nueva transacción
                $stmt = $this->db->prepare("
                    INSERT INTO transacciones (
                        usuario_id, cuenta_id, categoria_id, subcategoria_id, tipo, monto,
                        descripcion, fecha_transaccion, hora_transaccion, es_recurrente, 
                        gasto_recurrente_id, realizada, estado
                    ) VALUES (
                        :usuario_id, :cuenta_id, :categoria_id, :subcategoria_id, :tipo, :monto,
                        :descripcion, CURRENT_DATE(), CURRENT_TIME(), 1, 
                        :gasto_recurrente_id, :realizada, 1
                    )
                ");
                
                $stmt->execute([
                    'usuario_id' => $gasto['usuario_id'],
                    'cuenta_id' => $gasto['cuenta_id'],
                    'categoria_id' => $gasto['categoria_id'],
                    'subcategoria_id' => $gasto['subcategoria_id'],
                    'tipo' => $gasto['tipo'],
                    'monto' => $gasto['monto'],
                    'descripcion' => $gasto['nombre'],
                    'gasto_recurrente_id' => $gastoId,
                    'realizada' => $realizada ? 1 : 0
                ]);
                
                $transaccionId = (int) $this->db->lastInsertId();
                
                // Si está realizada, actualizar saldo
                if ($realizada) {
                    $signo = $gasto['tipo'] === 'ingreso' ? 1 : -1;
                    $stmtSaldo = $this->db->prepare("
                        UPDATE cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :cuenta_id
                    ");
                    $stmtSaldo->execute([
                        'monto' => $signo * $gasto['monto'],
                        'cuenta_id' => $gasto['cuenta_id']
                    ]);
                }
            }
            
            // Actualizar gasto recurrente
            $stmtGasto = $this->db->prepare("
                UPDATE gastos_recurrentes 
                SET ultima_ejecucion = CURRENT_DATE() 
                WHERE id = :id
            ");
            $stmtGasto->execute(['id' => $gastoId]);
            
            // Actualizar próxima ejecución
            $this->actualizarProximaEjecucion($gastoId);
            
            $this->db->commit();
            return $transaccionId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
