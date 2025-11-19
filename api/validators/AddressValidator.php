<?php
/**
 * AddressValidator.php
 * Validasi kelengkapan alamat pengiriman
 * Location: api/validators/AddressValidator.php
 */

class AddressValidator {
    
    private $confidenceScore = 100;
    private $errors = [];
    private $warnings = [];
    private $checks = [];
    
    /**
     * Main validation method
     */
    public function validate($addressData) {
        $this->resetValidation();
        
        // Required fields
        $requiredFields = [
            'nama_penerima' => 'Nama penerima',
            'no_telepon_penerima' => 'Nomor telepon',
            'alamat_lengkap' => 'Alamat lengkap'
        ];
        
        // Run validation checks
        $this->checkRequiredFields($addressData, $requiredFields);
        $this->validatePhoneNumber($addressData['no_telepon_penerima'] ?? '');
        $this->validateAddress($addressData['alamat_lengkap'] ?? '');
        $this->validateCity($addressData['kota'] ?? '');
        $this->validatePostalCode($addressData['kode_pos'] ?? '');
        
        return $this->getResults($addressData);
    }
    
    /**
     * CHECK 1: Required Fields
     */
    private function checkRequiredFields($data, $requiredFields) {
        foreach ($requiredFields as $field => $label) {
            if (empty($data[$field]) || trim($data[$field]) === '') {
                $this->addError("$label wajib diisi");
                $this->confidenceScore -= 25;
                $this->addCheck($label, 'fail', 'Kosong');
            } else {
                $this->addCheck($label, 'pass', 'Terisi');
            }
        }
    }
    
    /**
     * CHECK 2: Phone Number Format
     */
    private function validatePhoneNumber($phone) {
        if (empty($phone)) {
            return; // Already checked in required fields
        }
        
        // Remove spaces, dashes, parentheses
        $cleanPhone = preg_replace('/[\s\-\(\)]/', '', $phone);
        
        // Check if starts with valid prefix
        $validPrefixes = ['08', '628', '+628', '62'];
        $startsWithValid = false;
        
        foreach ($validPrefixes as $prefix) {
            if (strpos($cleanPhone, $prefix) === 0) {
                $startsWithValid = true;
                break;
            }
        }
        
        if (!$startsWithValid) {
            $this->addError('Nomor telepon harus diawali dengan 08 atau +62');
            $this->confidenceScore -= 20;
            $this->addCheck('Format Telepon', 'fail', $phone);
            return false;
        }
        
        // Check length (10-15 digits)
        $digitCount = preg_replace('/\D/', '', $cleanPhone);
        $length = strlen($digitCount);
        
        if ($length < 10 || $length > 15) {
            $this->addWarning('Nomor telepon terlalu pendek atau panjang (10-15 digit)');
            $this->confidenceScore -= 10;
            $this->addCheck('Panjang Telepon', 'warning', "$length digit");
        } else {
            $this->addCheck('Format Telepon', 'pass', $phone);
        }
        
        return true;
    }
    
    /**
     * CHECK 3: Address Completeness
     */
    private function validateAddress($address) {
        if (empty($address)) {
            return; // Already checked in required fields
        }
        
        $length = strlen(trim($address));
        
        // Check minimum length
        if ($length < 20) {
            $this->addWarning('Alamat terlalu singkat (min 20 karakter). Tambahkan detail seperti RT/RW, nama jalan');
            $this->confidenceScore -= 15;
            $this->addCheck('Kelengkapan Alamat', 'warning', "$length karakter");
            return false;
        }
        
        // Check for common keywords
        $keywords = ['jalan', 'jl', 'gang', 'gg', 'rt', 'rw', 'no', 'blok'];
        $foundKeywords = [];
        
        $lowerAddress = strtolower($address);
        foreach ($keywords as $keyword) {
            if (strpos($lowerAddress, $keyword) !== false) {
                $foundKeywords[] = $keyword;
            }
        }
        
        if (count($foundKeywords) < 2) {
            $this->addWarning('Alamat kurang detail. Sertakan nama jalan, RT/RW, atau nomor rumah');
            $this->confidenceScore -= 10;
        }
        
        $this->addCheck('Kelengkapan Alamat', 'pass', "$length karakter");
        return true;
    }
    
    /**
     * CHECK 4: City Name
     */
    private function validateCity($city) {
        if (empty($city)) {
            $this->addWarning('Kota belum diisi. Mohon lengkapi untuk memudahkan pengiriman');
            $this->confidenceScore -= 10;
            $this->addCheck('Kota', 'warning', 'Kosong');
            return false;
        }
        
        // Check if city name is realistic (at least 3 characters)
        if (strlen($city) < 3) {
            $this->addWarning('Nama kota terlalu pendek');
            $this->confidenceScore -= 5;
        }
        
        $this->addCheck('Kota', 'pass', $city);
        return true;
    }
    
    /**
     * CHECK 5: Postal Code (Optional but recommended)
     */
    private function validatePostalCode($postalCode) {
        if (empty($postalCode)) {
            $this->addWarning('Kode pos kosong. Disarankan diisi untuk mempercepat pengiriman');
            $this->confidenceScore -= 5;
            $this->addCheck('Kode Pos', 'warning', 'Kosong (opsional)');
            return false;
        }
        
        // Check format (5 digits for Indonesia)
        if (!preg_match('/^\d{5}$/', $postalCode)) {
            $this->addWarning('Format kode pos tidak valid (harus 5 digit angka)');
            $this->confidenceScore -= 5;
            $this->addCheck('Kode Pos', 'warning', $postalCode);
            return false;
        }
        
        $this->addCheck('Kode Pos', 'pass', $postalCode);
        return true;
    }
    
    /**
     * Helper methods
     */
    private function addError($message) {
        $this->errors[] = $message;
    }
    
    private function addWarning($message) {
        $this->warnings[] = $message;
    }
    
    private function addCheck($checkName, $status, $value) {
        $this->checks[] = [
            'check' => $checkName,
            'status' => $status,
            'value' => $value
        ];
    }
    
    private function resetValidation() {
        $this->confidenceScore = 100;
        $this->errors = [];
        $this->warnings = [];
        $this->checks = [];
    }
    
    /**
     * Get validation results
     */
    private function getResults($addressData) {
        $this->confidenceScore = max(0, $this->confidenceScore);
        $isValid = count($this->errors) === 0 && $this->confidenceScore >= 50;
        
        return [
            'is_valid' => $isValid,
            'confidence_score' => $this->confidenceScore,
            'address_data' => $addressData,
            'checks' => $this->checks,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'recommendation' => $this->getRecommendation($isValid, $this->confidenceScore),
            'validated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get recommendation
     */
    private function getRecommendation($isValid, $score) {
        if (!$isValid) {
            return 'REJECT - Alamat tidak lengkap. Minta customer melengkapi data alamat.';
        }
        
        if ($score >= 95) {
            return 'EXCELLENT - Alamat lengkap dan detail, siap untuk pengiriman.';
        }
        
        if ($score >= 85) {
            return 'GOOD - Alamat cukup lengkap, pengiriman dapat diproses.';
        }
        
        if ($score >= 70) {
            return 'ACCEPTABLE - Alamat minimal valid, tapi disarankan konfirmasi dengan customer.';
        }
        
        if ($score >= 50) {
            return 'NEEDS IMPROVEMENT - Alamat kurang detail. Hubungi customer untuk konfirmasi.';
        }
        
        return 'POOR - Alamat sangat minimal. Sangat disarankan konfirmasi dengan customer sebelum kirim.';
    }
    
    /**
     * Get formatted address for display
     */
    public function formatAddress($addressData) {
        $parts = [];
        
        if (!empty($addressData['alamat_lengkap'])) {
            $parts[] = $addressData['alamat_lengkap'];
        }
        
        if (!empty($addressData['kota'])) {
            $parts[] = $addressData['kota'];
        }
        
        if (!empty($addressData['provinsi'])) {
            $parts[] = $addressData['provinsi'];
        }
        
        if (!empty($addressData['kode_pos'])) {
            $parts[] = $addressData['kode_pos'];
        }
        
        return implode(', ', $parts);
    }
}
?>