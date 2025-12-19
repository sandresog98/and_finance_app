<?php
/**
 * Clase utilitaria para manejo seguro de subidas de archivos
 */

class FileUploadManager {
    
    /**
     * Genera un nombre único para un archivo
     */
    public static function generateUniqueFileName(string $originalName, string $prefix = '', string $userId = ''): string {
        $pathInfo = pathinfo(strtolower($originalName));
        $baseName = $pathInfo['filename'] ?? 'archivo';
        $extension = $pathInfo['extension'] ?? '';
        
        // Limpiar el nombre base
        $cleanBase = preg_replace('/[^a-z0-9_-]/', '-', $baseName);
        $cleanBase = preg_replace('/-+/', '-', $cleanBase);
        $cleanBase = trim($cleanBase, '-');
        if (empty($cleanBase)) {
            $cleanBase = 'archivo';
        }
        
        // Crear prefijo único
        $uniquePrefix = '';
        if (!empty($prefix)) {
            $uniquePrefix = $prefix . '_';
        }
        if (!empty($userId)) {
            $uniquePrefix .= 'user' . $userId . '_';
        }
        
        // Generar timestamp y identificador único
        $timestamp = date('Ymd_His');
        $uniqueId = substr(uniqid('', true), -8);
        
        // Construir nombre final
        $fileName = $uniquePrefix . $cleanBase . '_' . $timestamp . '_' . $uniqueId;
        
        if (!empty($extension)) {
            $fileName .= '.' . $extension;
        }
        
        return $fileName;
    }
    
    /**
     * Valida y guarda un archivo subido
     */
    public static function saveUploadedFile(array $file, string $destinationDir, array $options = []): array {
        $defaults = [
            'maxSize' => 5 * 1024 * 1024, // 5MB por defecto
            'allowedExtensions' => ['jpg', 'jpeg', 'png', 'pdf'],
            'prefix' => '',
            'userId' => '',
            'createSubdirs' => true,
            'webPath' => ''
        ];
        
        $config = array_merge($defaults, $options);
        
        // Validar archivo
        if (!isset($file) || !is_array($file)) {
            throw new Exception('Archivo no válido');
        }
        
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('Error en la subida del archivo');
        }
        
        $originalName = $file['name'] ?? '';
        $tmpName = $file['tmp_name'] ?? '';
        $size = (int)($file['size'] ?? 0);
        
        if (empty($originalName) || $size <= 0) {
            throw new Exception('Archivo inválido');
        }
        
        if ($size > $config['maxSize']) {
            throw new Exception('Archivo demasiado grande. Máximo: ' . self::formatBytes($config['maxSize']));
        }
        
        // Validar extensión
        $pathInfo = pathinfo(strtolower($originalName));
        $extension = $pathInfo['extension'] ?? '';
        
        if (!in_array($extension, $config['allowedExtensions'])) {
            throw new Exception('Tipo de archivo no permitido. Permitidos: ' . implode(', ', $config['allowedExtensions']));
        }
        
        // Crear directorio si no existe
        if (!is_dir($destinationDir)) {
            if (!mkdir($destinationDir, 0777, true)) {
                throw new Exception('No se pudo crear el directorio de destino: ' . $destinationDir);
            }
            // Asegurar permisos de escritura
            chmod($destinationDir, 0777);
        }
        
        // Verificar que el directorio sea escribible
        if (!is_writable($destinationDir)) {
            throw new Exception('El directorio de destino no tiene permisos de escritura: ' . $destinationDir);
        }
        
        // Crear subdirectorios por año/mes si está habilitado
        if ($config['createSubdirs']) {
            $year = date('Y');
            $month = date('m');
            $subDir = rtrim($destinationDir, '/') . '/' . $year;
            
            // Crear directorio del año si no existe
            if (!is_dir($subDir)) {
                if (!mkdir($subDir, 0777, true)) {
                    throw new Exception('No se pudo crear el directorio del año: ' . $subDir);
                }
                chmod($subDir, 0777);
            }
            
            // Crear directorio del mes
            $destinationDir = $subDir . '/' . $month;
            if (!is_dir($destinationDir)) {
                if (!mkdir($destinationDir, 0777, true)) {
                    throw new Exception('No se pudo crear el subdirectorio del mes: ' . $destinationDir);
                }
                chmod($destinationDir, 0777);
            }
        }
        
        // Generar nombre único
        $uniqueFileName = self::generateUniqueFileName($originalName, $config['prefix'], $config['userId']);
        $destinationPath = rtrim($destinationDir, '/') . '/' . $uniqueFileName;
        
        // Mover archivo
        if (!move_uploaded_file($tmpName, $destinationPath)) {
            throw new Exception('No se pudo guardar el archivo');
        }
        
        // Construir ruta web (igual que en we_are_app)
        $webUrl = '';
        if (!empty($config['webPath'])) {
            if ($config['createSubdirs']) {
                $year = date('Y');
                $month = date('m');
                $webUrl = rtrim($config['webPath'], '/') . '/' . $year . '/' . $month . '/' . $uniqueFileName;
            } else {
                $webUrl = rtrim($config['webPath'], '/') . '/' . $uniqueFileName;
            }
        }
        
        return [
            'success' => true,
            'original_name' => $originalName,
            'file_name' => $uniqueFileName,
            'file_path' => $destinationPath,
            'web_path' => $webUrl, // Mantener compatibilidad
            'webUrl' => $webUrl,   // Agregar webUrl como en we_are_app
            'size' => $size,
            'mime_type' => $file['type'] ?? mime_content_type($destinationPath)
        ];
    }
    
    /**
     * Formatea bytes a formato legible
     */
    public static function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
