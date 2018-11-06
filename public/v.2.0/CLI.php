<?php
header('Content-Type: text/html; charset=utf-8', true);
require ('../../vendor/autoload.php');
class Parser {

    #Base parse url
    static $url = 'https://www.avtoall.ru/catalog/vaz-3/';

    #host
    static $host = 'https://www.avtoall.ru';

    # Referer
    static $refer = 'http://google.com/';

    #Mysql Config
    static $mysql = [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => 'secret',
        'db'   => 'parser'
        ];

    #CarLinks and Name
    static $Cars = [];

    #Mysql Link
    static  $MysqlLink;
    /**
     * @param $url
     * @param $host
     * @param $refer
     * @param $mysql
     */

    static function parseInit()
    {

        /*
        self::$url = $url;
        self::$host = $host;
        self::$refer = $refer;
        self::$mysql = $mysql;
        */
        
        //---------------------------------------------ПОПЫТКА СОЕДИНЕНИЯ С MYSQL---------------------------------------------//
        try
        {

            self::$mysql = self::mysqlInit();

            if(!self::$mysql)
            {
                throw new Exception('Could connect to Mysql Database');
            }
            #Установка кодировки UTF8
            self::$mysql->set_charset('utf8');
        }

        catch (Exception $ex)
        {
            $ex->getMessage();
        }
        //---------------------------------------------------------END--------------------------------------------------------//
        //-------------------------------------------------ПОПЫТКА ПОЛУЧИТЬ HTML----------------------------------------------//
        try
        {
            self::$Cars = self::getCars(self::$url);

            if(!self::$Cars)
            {
                throw new Exception('Html data not found, connection aborted, check self::$url');
            }


        }
        catch (Exception $ex)
        {
            $ex->getMessage();
            exit();
        }
        //---------------------------------------------------------END--------------------------------------------------------//

    }

    /**
     * @return mysqli
     */
    static function mysqlInit()
    {
        $mysql = @mysqli_connect(self::$mysql['host'],self::$mysql['user'],self::$mysql['pass'],self::$mysql['db']);

        if($mysql)
        {
            return $mysql;
        }
    }
    /**
     * Init Curl func
     * @param $url
     * @param $referer
     * @return mixed HTML
     */
    static function curlInit($url)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL,$url);
        curl_setopt($curl, CURLOPT_HEADER,0);
        curl_setopt($curl, CURLOPT_USERAGENT,'Google Chrome Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36.');
        curl_setopt($curl, CURLOPT_REFERER,self::$refer);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;

    }

    /**
     * @param $url - Base url of list cars
     * @param $referer - Опция CURL для HTTP_REFERER
     * @param $host - Базовый url сайта, для допиливания ссылок
     * @return array - Возвращает массив всех ссылок на автомобили
     */
    static function getCars($url)
    {
        $data = self::curlInit($url); //получаем html страницу

        $document = phpQuery::newDocument($data);
        $document = pq($document);
        $link = $document->find('.color-block ul li b')->children('.model_item');

        $cars= [];
        $i=0;
        foreach ($link as $car) {
            $pq = pq($car);
            $cars[$i]['name'] = $pq->text(); //название автомобиля
            $cars[$i]['link'] = self::$host.$pq->attr('href');
            #echo " \e[01;33m Автомобиль ".$cars[$i]['name']." добавлен в список  \e[0m\n";
            $i++;

        }
        $document->unloadDocument();

        return $cars;

    }

    /**
     * @param $href - Линк на автомобиль полученный из getCars
     * @param $referer - откуда пришли
     * @param $host - базовый url сайта, для допиливания ссылок
     * @param $carName - Имя автомобиля полученное из getCars
     * @return array - возвращает массив ссылок на детали автомобиля с названием детали и авто
     */
    static function getCar($href, $host, $carName)
    {
        $data = self::curlInit($href);
        $document = phpQuery::newDocument($data);
        pq($document);
        $carPartsLinks = $document->find('#autoparts_tree ul li')->children('a');
        $parts= [];
        $i=0;
        foreach ($carPartsLinks as $car) {
            $pq = pq($car);

            $parts[$i]['carName'] = $carName; //название тачки
            $parts[$i]['name'] = $pq->text();
            $parts[$i]['link'] = $host.$pq->attr('href');

            //comment in cmd
            echo " \e[01;33m Категория ".$parts[$i]['name']." для автомобиля $carName добавлена в список  \e[0m\n";

            $i++;

        }
        $document->unloadDocument();
        //comment in cmd
        echo " \e[01;32m Список категорий деталей получен.. \e[0m\n";

        return $parts;

    }

    /**
     * @param $url - Адрес на страницу с картинкой
     * @return string - url картинки
     */
    static function saveImage($url)
    {
        $filename = md5($url).'.gif';
        $image = curl_init($url);
        $fp = fopen('images/'.$filename, 'w+');
        curl_setopt($image, CURLOPT_FILE, $fp);
        curl_setopt($image, CURLOPT_HEADER, 0);
        curl_setopt($image, CURLOPT_HEADER,0);
        curl_setopt($image, CURLOPT_USERAGENT,'Google Chrome Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36.');
        curl_setopt($image, CURLOPT_REFERER,self::$refer);
        curl_exec($image);
        curl_close($image);
        fclose($fp);

        echo " \e[01;32m Изображение сохранено в папку images/$filename \e[0m\n";

        return $filename;


    }

    /**
     * @param $href - Ссылка на запчасть
     * @param $car_name - Название автомобиля
     * @param $category - Категория запчасти
     * @param $link - Линк на обьект БД MySQL для отслеживания ошибок
     */
    static function getPart($href, $car_name, $category, $link)
    {
        $data = Parser::curlInit($href);
        $document = phpQuery::newDocument($data);
        $title = $document->find('.color-block')->children('h1')->text();
        $image = $document->find('#picture_img')->attr('src');
        $goodsTable = $document->find('.parts')->children('table')->html();
        $goodsTable = mysqli_real_escape_string($link,$goodsTable);
        if(empty($image)){
            echo " \e[01;31m Изображение отсутствует, левая ссылка на скрипт, информация добавлена не будет. \e[0m\n";
            echo " \e[01;31m Ссылка: $href \e[0m\n";
            $document->unloadDocument();
        }else{

            echo " \e[01;32m Получаю Изображение \e[0m\n";
            $filename = 'images/'.self::saveImage($image); //Сохраняем картинку

            echo "\e[5mBlink \e[01;32m Сохраняю изображение \e[0m\n";


            echo " \e[01;32m Добавляю информацию в базу данных \e[0m\n";
            $query = "INSERT INTO `Parts` (id,carName,carPartCategory,carPart,image,goodsTable) VALUES(NULL,'".$car_name."','".$category."','".$title."','".$filename."','".$goodsTable."');";
            mysqli_query($link,$query) or die(mysqli_error($link));

            echo " \e[01;32m Информация о детали $title для автомобиля $car_name сохранена успешно! \e[0m\n";
            $document->unloadDocument();
        }

    }


    static function debug($var)
    {
        //DEBUG//
        echo PHP_EOL;
        print_r($var);
        echo PHP_EOL;
        //---------//
    }


}

###################EXEC########################

//***************TIME START*****************//
    $start = microtime(true);
//******************************************//

//************************Получить список всех авто************************//

    Parser::parseInit();

    if(Parser::$mysql)
    {
        //comment in cmd
        echo " \e[01;32m Подключение к БД успешно \e[0m\n";
    }
//Добавляем в переменную CARS массив car['name'] car['link']
    $Cars = Parser::$Cars;

    if($Cars)
    {
        //comment in cmd
        echo " \e[01;32m Список авто получен.. \e[0m\n";
    }
//*************************************************************************//


//************************Получить список деталей по конкретному автомобилю************************//

//*************************************************************************************************//

//Пройтись по каждой детали, сохранить картинку и инфо в БД
#getCar($href, $host, $carName);

foreach ($Cars as $car)
 {
    $detailList = Parser::getCar($car['link'],Parser::$host,$car['name']);
    foreach ($detailList as $detail)
    {
        #getPart($href,$car_name,$category,$link)
        Parser::getPart($detail['link'],$detail['carName'],$detail['name'],Parser::$mysql);

    }

}


//***************TIME END*****************//
 $time = microtime(true) - $start;
 print ('Время выполнения скрипта: '.$time.PHP_EOL);
//****************************************//