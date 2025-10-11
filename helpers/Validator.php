<?php
/**
 * File: helpers/Validator.php
 * Helper untuk validasi input
 */

class Validator {
    
    private $errors = [];
    private $data = [];

    public function __construct($data) {
        $this->data = $data;
    }

    /**
     * Validasi required (wajib diisi)
     */
    public function required($field, $message = null) {
        if (!isset($this->data[$field]) || empty(trim($this->data[$field]))) {
            $this->errors[$field] = $message ?? "Field {$field} wajib diisi";
        }
        return $this;
    }

    /**
     * Validasi email
     */
    public function email($field, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field] = $message ?? "Format email tidak valid";
            }
        }
        return $this;
    }

    /**
     * Validasi numeric
     */
    public function numeric($field, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!is_numeric($this->data[$field])) {
                $this->errors[$field] = $message ?? "Field {$field} harus berupa angka";
            }
        }
        return $this;
    }

    /**
     * Validasi min length
     */
    public function minLength($field, $min, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (strlen($this->data[$field]) < $min) {
                $this->errors[$field] = $message ?? "Field {$field} minimal {$min} karakter";
            }
        }
        return $this;
    }

    /**
     * Validasi max length
     */
    public function maxLength($field, $max, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (strlen($this->data[$field]) > $max) {
                $this->errors[$field] = $message ?? "Field {$field} maksimal {$max} karakter";
            }
        }
        return $this;
    }

    /**
     * Validasi min value (untuk angka)
     */
    public function min($field, $min, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if ($this->data[$field] < $min) {
                $this->errors[$field] = $message ?? "Field {$field} minimal {$min}";
            }
        }
        return $this;
    }

    /**
     * Validasi max value (untuk angka)
     */
    public function max($field, $max, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if ($this->data[$field] > $max) {
                $this->errors[$field] = $message ?? "Field {$field} maksimal {$max}";
            }
        }
        return $this;
    }

    /**
     * Validasi enum (harus salah satu dari pilihan)
     */
    public function enum($field, $options, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!in_array($this->data[$field], $options)) {
                $this->errors[$field] = $message ?? "Field {$field} harus salah satu dari: " . implode(', ', $options);
            }
        }
        return $this;
    }

    /**
     * Validasi phone number (Indonesia)
     */
    public function phone($field, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $phone = preg_replace('/[^0-9]/', '', $this->data[$field]);
            if (!preg_match('/^(08|628|\+628)[0-9]{8,11}$/', $phone)) {
                $this->errors[$field] = $message ?? "Format nomor telepon tidak valid";
            }
        }
        return $this;
    }

    /**
     * Validasi date (format Y-m-d)
     */
    public function date($field, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $d = \DateTime::createFromFormat('Y-m-d', $this->data[$field]);
            if (!$d || $d->format('Y-m-d') !== $this->data[$field]) {
                $this->errors[$field] = $message ?? "Format tanggal tidak valid (gunakan: YYYY-MM-DD)";
            }
        }
        return $this;
    }

    /**
     * Validasi datetime (format Y-m-d H:i:s)
     */
    public function datetime($field, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $d = \DateTime::createFromFormat('Y-m-d H:i:s', $this->data[$field]);
            if (!$d || $d->format('Y-m-d H:i:s') !== $this->data[$field]) {
                $this->errors[$field] = $message ?? "Format datetime tidak valid (gunakan: YYYY-MM-DD HH:MM:SS)";
            }
        }
        return $this;
    }

    /**
     * Validasi unique di database
     */
    public function unique($field, $table, $column, $db, $excludeId = null, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $value = $db->escape($this->data[$field]);
            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = '{$value}'";
            
            if ($excludeId) {
                $sql .= " AND id != '{$excludeId}'";
            }
            
            $result = $db->query($sql);
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $this->errors[$field] = $message ?? "Field {$field} sudah digunakan";
            }
        }
        return $this;
    }

    /**
     * Validasi exists di database
     */
    public function exists($field, $table, $column, $db, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $value = $db->escape($this->data[$field]);
            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = '{$value}'";
            $result = $db->query($sql);
            $row = $result->fetch_assoc();
            
            if ($row['count'] == 0) {
                $this->errors[$field] = $message ?? "Field {$field} tidak ditemukan";
            }
        }
        return $this;
    }

    /**
     * Custom validation dengan callback
     */
    public function custom($field, $callback, $message) {
        if (isset($this->data[$field])) {
            if (!$callback($this->data[$field])) {
                $this->errors[$field] = $message;
            }
        }
        return $this;
    }

    /**
     * Check apakah ada error
     */
    public function fails() {
        return !empty($this->errors);
    }

    /**
     * Get semua error
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get data yang sudah divalidasi
     */
    public function validated() {
        return $this->data;
    }
}

/**
 * Helper function untuk membuat validator
 */
function validate($data) {
    return new Validator($data);
}
?>