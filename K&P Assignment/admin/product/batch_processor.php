<?php
require_once '_base.php';

class BatchProcessor {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function processCsvImport($file) {
        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle);
            $results = ['success' => 0, 'failed' => 0, 'errors' => []];
            
            try {
                $this->db->beginTransaction();
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    try {
                        $product = array_combine($header, $data);
                        $this->insertProduct($product);
                        $results['success']++;
                    } catch (Exception $e) {
                        $results['failed']++;
                        $results['errors'][] = "Row error: " . $e->getMessage();
                    }
                }
                
                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
            fclose($handle);
            return $results;
        }
        throw new Exception("Could not open file");
    }
    
    public function batchUpdatePrices($category_id, $adjustment_type, $value) {
        try {
            $this->db->beginTransaction();
            
            $sql = "UPDATE product SET product_price = CASE 
                    WHEN ? = 'increase' THEN product_price * (1 + ?/100)
                    WHEN ? = 'decrease' THEN product_price * (1 - ?/100)
                    ELSE product_price END
                    WHERE category_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adjustment_type, $value, $adjustment_type, $value, $category_id]);
            
            $this->db->commit();
            return $stmt->rowCount();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    private function insertProduct($data) {
        // Validate required fields
        $required = ['product_name', 'category_id', 'product_price'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Generate product ID
        $stmt = $this->db->query("SELECT MAX(CAST(SUBSTRING(product_id, 2) AS UNSIGNED)) FROM product");
        $maxId = $stmt->fetchColumn();
        $nextId = 'P' . str_pad(($maxId + 1), 3, '0', STR_PAD_LEFT);
        
        // Insert product
        $sql = "INSERT INTO product (product_id, product_name, category_id, product_price, 
                product_description, product_type, product_status) 
                VALUES (?, ?, ?, ?, ?, ?, 'Available')";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $nextId,
            $data['product_name'],
            $data['category_id'],
            $data['product_price'],
            $data['product_description'] ?? '',
            $data['product_type'] ?? 'Unisex'
        ]);
        
        // Insert stock quantities
        $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
        foreach ($sizes as $size) {
            if (isset($data['stock_' . $size]) && $data['stock_' . $size] > 0) {
                $sql = "INSERT INTO quantity (product_id, size, product_stock, product_sold) 
                        VALUES (?, ?, ?, 0)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$nextId, $size, $data['stock_' . $size]]);
            }
        }
        
        return $nextId;
    }
}
?>