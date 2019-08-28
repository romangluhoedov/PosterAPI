<?php

require_once('config.php');
require_once __DIR__ . '/../vendor/autoload.php';
require_once('DBClass.php');
require_once('Munch.php');
$logger = new \Katzgrau\KLogger\Logger( __DIR__ . '/../logs/write-off');

class PosterApi {
    const COOK_METHODS = [
        'pr_in_clear' => 'Очистка',
        'pr_in_cook' => 'Варка',
        'pr_in_fry' => 'Жарка',
        'pr_in_stew' => 'Тушение',
        'pr_in_bake' => 'Запекание'
    ];

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null)
        {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public function sendRequest($action, array $params = [], $type = 'get', $json = true)
    {
        $params = array_merge($params, [
            'format' => 'json',
            'token' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
        ]);

        $url = 'https://joinposter.com/api/' . $action . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($type == 'post' || $type == 'put') {
            curl_setopt($ch, CURLOPT_POST, true);

            if ($json) {
                $params = json_encode($params);

                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($params)
                ]);

                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            }
        }

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Poster (http://joinposter.com)');

        $data = curl_exec($ch);
        curl_close($ch);

        return ($json == true) ? json_decode($data) : $data;
    }

    /**
     * @return bool|mixed|string
     */
    public function getBatchtickets()
    {
        return $this->sendRequest('menu.getProducts', [
            'type' => 'batchtickets'
        ]);
    }

    /**
     * @param $id
     * @return bool|mixed|string
     */
    public function getBatchticketById($id)
    {
        return $this->sendRequest('menu.getProduct', [
            'product_id' => $id
        ]);
    }

    /**
     * @param $id
     * @return bool|mixed|string
     */
    public function getPrepackById($id)
    {
        return $this->sendRequest('menu.getPrepack', [
            'product_id' => $id
        ]);
    }

    /**
     * @return bool|mixed|string
     */
    public function getPrepacks()
    {
        return $this->sendRequest('menu.getPrepacks');
    }

    /**
     * @return bool|mixed|string
     */
    public function getCategories()
    {
        return $this->sendRequest('menu.getCategories');
    }

    /**
     * @param $id
     * @return bool|mixed|string
     */
    public function getCategoryById($id)
    {
        return $this->sendRequest('menu.getCategory', [
            'category_id' => $id
        ]);
    }

    /**
     * @param string $dateFrom
     * @param string $dateTo
     * @param string $limit
     * @param string $offset
     * @return bool|mixed|string
     */
    public function getSupplies($dateFrom = '', $dateTo = '', $limit = '', $offset = '')
    {
        return $this->sendRequest('storage.getSupplies', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * @param $supplyId
     * @return bool|mixed|string
     */
    public function getSupplyIngredients($supplyId)
    {
        return $this->sendRequest('storage.getSupplyIngredients', [
            'supply_id' => $supplyId
        ]);
    }

    /**
     * @param $date
     * @param array $object
     * @param int $storageID
     * @return bool|mixed|string
     */
    public function createWriteOff($date, array $object, $storageID = 1 /*Store "Kitchen"*/)
    {
        return $this->sendRequest('storage.createWriteOff', [
            "write_off" => [
                "storage_id"    => $storageID,
                "date"          => $date,
            ],
            "ingredient" => [
                $object
            ]
        ], 'post');
    }

    /**
     * @param $objectId
     * @param $weight
     * @param $objectType
     * @param string $date
     * @return bool|mixed|string
     */
    public function createWriteOffById($objectId, $weight, $objectType, $date = '') {

        if (empty($date))
            $date = date('Y-m-d H:i:s');

        return $this->createWriteOff($date, [
            'id' => $objectId,
            'weight' => $weight,
            'type' => $objectType
        ]);

    }

    /**
     * @param string $weight
     */
    public function createWriteOffStuff($weight = '')
    {
        $config = include('config.php');
        $logger = new \Katzgrau\KLogger\Logger($_SERVER['DOCUMENT_ROOT'] . '/logs/write-off');

        $date = date('Y-m-d H:i:s');

        $deliveryStuff = $config['deliveryStuff'];

        foreach ($deliveryStuff as $item) {

            $weight = (!empty($weight)) ? $weight * $item['weight'] : $item['weight'];

            $this->createWriteOff($date, [
                'id' => $item['id'],
                'weight' => $weight,
                'type' => $item['type']
            ]);
        }

        $logger->debug('Write off extra stuff: ', $deliveryStuff);
    }

    /**
     * @param $id
     * @param string $type
     * @return bool|mixed|void
     */
    public function getInfo($id, $type = 'batchticket')
    {

        switch ($type) {
            case 'batchticket':
                $table_name = 'batchtickets_ingredients';
                $column_name = 'batchticket_id';
                break;
            case 'prepack':
                $table_name = 'prepacks_ingredients';
                $column_name = 'prepack_id';
                break;
            default:
                $table_name = 'batchtickets_ingredients';
                $column_name = 'batchticket_id';
        }

        $db = new DBClass();
        $db_ingredient = $db->fetchAll($db->query('SELECT * FROM '.$table_name.' WHERE '.$column_name.' = "'.$id.'"'));

        if (!empty($db_ingredient[0][$column_name])) {
            $db->close();

            if (empty($db_ingredient[0]['ingredients'])) return;

            $info = unserialize(stripslashes($db_ingredient[0]['ingredients']));

            return $info;
        }
        else {
            return false;
        }
    }

    /**
     * @return array
     */
    public function getIngredients()
    {
        $ingredients = $this->sendRequest('menu.getIngredients');
        $preparedIngredients = [];
        foreach ($ingredients->response as $key => $ingredient) {
            $preparedIngredients[$ingredient->ingredient_id] = $ingredient;
            unset($ingredients->response[$key]);
        }

        return $preparedIngredients;
    }

    /**
     * @return bool|string
     */
    public function insertBatchticketsFromPoster()
    {
        $items = $this->getBatchtickets()->response;

        $insert_values = '';
        $insert_values_prepacks = '';

        if (!empty($items)) {
            $counter = 0;
            $len = count($items);
            foreach ($items as $item) {

                $cat_title = $item->category_name;

                $comma = ($counter == $len - 1) ? '' : ',';

                $insert_values .= "({$item->product_id}, '{$item->product_name}', '{$cat_title}'){$comma} ";

                $prepacks_counter = 0;
                $prepacks_len = count($item->ingredients);

                foreach ($item->ingredients as $ingredient) {

                    $p_comma = ($prepacks_counter == $prepacks_len - 1 && $counter == $len - 1) ? '' : ',';

                    $insert_values_prepacks .= "({$ingredient->ingredient_id}, {$item->product_id}, '{$ingredient->ingredient_name}'){$p_comma} ";

                    $prepacks_counter++;
                }

                $counter++;

            }
        }
        else {
            return false;
        }

        $db = new DBClass();
        $query = "INSERT INTO batchtickets (batchticket_id, title, program_type) VALUES " . $insert_values .
            'ON DUPLICATE KEY UPDATE batchticket_id=VALUES(batchticket_id),title=VALUES(title),program_type=VALUES(program_type)';

        $query_prepacks = "INSERT INTO prepacks (prepack_id, batchticket_id, prepack_title) VALUES " . $insert_values_prepacks .
            'ON DUPLICATE KEY UPDATE prepack_id=VALUES(prepack_id),batchticket_id=VALUES(batchticket_id), prepack_title=VALUES(prepack_title)';

        $db->query($query);
        $db->query($query_prepacks);

        $db->close();

        return $query;
    }

    /**
     * @return bool
     */
    public function checkIfDeleteFromPoster()
    {
        $posterItems = [];

        $items = $this->getBatchtickets()->response;

        // TODO Check for deleted prepacks

        foreach ($items as $i) {
            $posterItems[$i->category_name][] = $i->product_id;
        }

        $dbItems = Munch::getAllBatchtickets();
        foreach ($dbItems as $i) {
            if (!in_array($i['id'], $posterItems[$i['program_type']])) {

                $db = new DBClass();

                $del_from_calendar = "DELETE FROM calendar WHERE batchticket_id = ".$i['id'];

                $db->query($del_from_calendar);

                $del_from_dishes = "DELETE FROM batchtickets WHERE id = ".$i['id'];

                $db->query($del_from_dishes);

                $db->close();

            }
        }

        $result = (!empty($items)) ? true : false;

        return $result;

    }
}
