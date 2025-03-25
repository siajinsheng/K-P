<?php

class SimplePager {
    public $limit;      // Page size
    public $page;       // Current page
    public $item_count; // Total item count
    public $page_count; // Total page count
    public $result;     // Result set (array of records)
    public $count;      // Item count on the current page

    public function __construct($query, $params, $limit, $page) {
        global $_db;

        // Ensure limit and page are integers
        $this->limit = is_numeric($limit) ? max((int)$limit, 1) : 10;
        $this->page = is_numeric($page) ? max((int)$page, 1) : 1;

        // Set [item count]
        $q = preg_replace('/SELECT.+FROM/', 'SELECT COUNT(*) FROM', $query, 1);
        $stm = $_db->prepare($q);
        $stm->execute($params);
        $this->item_count = (int)$stm->fetchColumn();

        // Set [page count]
        $this->page_count = ceil($this->item_count / $this->limit);

        // Calculate offset
        $offset = ($this->page - 1) * $this->limit;

        // Set [result]
        $stm = $_db->prepare($query . " LIMIT $offset, $this->limit");
        $stm->execute($params);
        $this->result = $stm->fetchAll(PDO::FETCH_OBJ);

        // Set [count]
        $this->count = count($this->result);
    }

    public function html($href = '', $attr = '') {
        if (!$this->result) return;
    
        // Generate pager (html)
        $prev = max($this->page - 1, 1);
        $next = min($this->page + 1, $this->page_count);
    
        echo "<nav class='pager' $attr>";
        echo "<ul class='pagination'>";
        echo "<li><a href='?page=1&$href'>First</a></li>";
        echo "<li><a href='?page=$prev&$href'>Previous</a></li>";
    
        for ($p = 1; $p <= $this->page_count; $p++) {
            $active = $p == $this->page ? 'active' : '';
            echo "<li class='$active'><a href='?page=$p&$href'>$p</a></li>";
        }
    
        echo "<li><a href='?page=$next&$href'>Next</a></li>";
        echo "<li><a href='?page=$this->page_count&$href'>Last</a></li>";
        echo "</ul>";
        echo "</nav>";
    }    
}