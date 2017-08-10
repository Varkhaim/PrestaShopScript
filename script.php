<?php

function Connect()
{
    $mypdo = new PDO("mysql:host=localhost;dbname=prestashop", "root", "");
    $mypdo -> query ('SET NAMES utf8');
    $mypdo -> query ('SET CHARACTER_SET utf8_unicode_ci');
    return $mypdo;
}

function GetCapacity($myProduct)
{
    $productData = explode("-",$myProduct);
    $capacity = ltrim($productData[1],"0");
    if (is1000L($myProduct))
        $capacity = "1000";

    if (isDesign($myProduct))
    {
        $capacity .= "D";
    }

    if ($productData[2] == "05")
    {
        $capacity .= "ML";
    }

    return $capacity;
}

function isDesign($myProduct)
{
    $productData = explode("-",$myProduct);
    $special = $productData[3];
    if ($special == "888")
        return true;
    else {
        return false;
    }
}

function is1000L($myProduct)
{
    $productData = explode("-",$myProduct);
    $special = $productData[1]."-".$productData[2]."-".$productData[3];
    if (($special == "001-06-999") || ($special == "700-01-999"))
        return true;
    else
        return false;
}

function GetProducts()
{
    $mypdo = Connect();

    $sql = "SELECT id_product, reference FROM ps_product";
    $st = $mypdo->prepare($sql);
    $st->execute();

    $row = $st->fetchAll();
    $dbconnect = null;

    return $row;
}

class Oil
{
    public $productID = null;
    public $reference = null;

    function __construct($id, $ref)
    {
        $this->productID = $id;
        $this->reference = $ref;
    }

    static function GetCapacityAttribute($capacity)
    {
        switch ($capacity)
        {
            case "1": return 25; break; // 1 litr
            case "4": return 26; break; // 4 litry
            case "1000": return 27; break; // 1000 litrow
            case "5": return 28; break; // 5 litr
            case "10": return 29; break; // 10 litrow
            case "20": return 30; break; // 20 litrow
            case "60": return 31; break; // 60 litrow
            case "208": return 32; break; // 208 litrow
            case "60D": return 33; break; // 60 litrow DESIGN
            case "208D": return 34; break; // 208 litrow DESIGN
            case "20D": return 35; break; // 20 litrow DESIGN
            case "50ML": return 36; break; // 50 ml
            case "100ML": return 37; break; // 100 ml
            case "250ML": return 38; break; // 250 ml
            case "400ML": return 39; break; // 400 ml
            case "500ML": return 40; break; // 500 ml
            case "300ML": return 41; break; // 300 ml
            case "150": return 42; break; // 1,5 litra
        }
    }

    public function AddVariant($id, $product, $save=false)
    {
        $mypdo = Connect();

        $capAtt = Oil::GetCapacityAttribute(GetCapacity($product)); // pobiera id_attribute

        $def = null;
        if ($save) $def = 1;

        // w ps_product_attribute wrzucamy nowa kombinacje do primary produktu
        $sql = "INSERT INTO ps_product_attribute (id_product, reference, default_on) VALUES (:prodID, :ref, :def)";
        $st = $mypdo->prepare($sql);
        $st->bindValue(":prodID", $this->productID, PDO::PARAM_INT);
        $st->bindValue(":def", $def, PDO::PARAM_INT);
        $st->bindValue(":ref", $product, PDO::PARAM_STR);
        $st->execute();

        // potem pobieramy nowo utworzony rekord i bierzemy z niego id_product_attribute
        $sql = "SELECT id_product_attribute FROM ps_product_attribute ORDER BY id_product_attribute DESC LIMIT 1;";
        $st = $mypdo->prepare($sql);
        $st->execute();
        $row = $st->fetch();
        $prodAttr = $row['id_product_attribute'];

        // w ps_product_attribute_shop tez trzeba to wrzucic
        $sql = "INSERT INTO ps_product_attribute_shop (id_product_attribute, id_product, default_on, id_shop) VALUES (:prodAttrID, :prodID, :def, 1)";
        $st = $mypdo->prepare($sql);
        $st->bindValue(":prodID", $this->productID, PDO::PARAM_INT);
        $st->bindValue(":prodAttrID", $prodAttr, PDO::PARAM_INT);
        $st->bindValue(":def", $def, PDO::PARAM_INT);
        $st->execute();

        // teraz do kombinacji trzeba przypisac atrybuty
        $sql = "INSERT INTO ps_product_attribute_combination (id_attribute, id_product_attribute) VALUES (:attrID, :prodAttrID)";
        $st = $mypdo->prepare($sql);
        $st->bindValue(":attrID", $capAtt, PDO::PARAM_INT);
        $st->bindValue(":prodAttrID", $prodAttr, PDO::PARAM_INT);
        $st->execute();

        // pobieranie id obrazka
        $sql = "SELECT id_image FROM ps_image WHERE id_product=:prodID";
        $st = $mypdo->prepare($sql);
        $st->bindValue(":prodID", $id, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        $imageID = $row['id_image'];

        // przypisywanie obrazka do kombinacji
        $sql = "INSERT INTO ps_product_attribute_image VALUES (:prodAttrID, :imgID)";
        $st = $mypdo->prepare($sql);
        $st->bindValue(":prodAttrID", $prodAttr, PDO::PARAM_INT);
        $st->bindValue(":imgID", $imageID, PDO::PARAM_INT);
        $st->execute();

        // przypisywanie obrazka do kombinacji
        $cover = null;
        if ($save) $cover = 1;

        $sql = "INSERT INTO ps_image_shop VALUES (:imgID, 1, :cover, :prodID)";
        $st = $mypdo->prepare($sql);
        $st->bindValue(":imgID", $imageID, PDO::PARAM_INT);
        $st->bindValue(":cover", $cover, PDO::PARAM_INT);
        $st->bindValue(":prodID", $this->productID, PDO::PARAM_INT);
        $st->execute();

        $sql = "UPDATE ps_image SET id_product=:prodID, cover=:cover WHERE id_image=:imgID";
        $st = $mypdo->prepare($sql);
        $st->bindValue(":imgID", $imageID, PDO::PARAM_INT);
        $st->bindValue(":cover", $cover, PDO::PARAM_INT);
        $st->bindValue(":prodID", $this->productID, PDO::PARAM_INT);
        $st->execute();

        if (!$save) {
            $sql = "DELETE FROM ps_product WHERE id_product = :prodID";
            $st = $mypdo->prepare($sql);
            $st->bindValue(":prodID", $id, PDO::PARAM_INT);
            $st->execute();
        }

        $dbconnect = null;

    }
}

function ClearDB()
{
    $mypdo = Connect();

    // czyszczenie ps_product_attribute oraz ps_product_attribute_combination
    $sql = "DELETE FROM ps_product_attribute;
DELETE FROM ps_product_attribute_combination;
DELETE FROM ps_product_attribute_shop;
DELETE FROM ps_product_attribute_image;
DELETE FROM ps_image_shop;
";
    $st = $mypdo->prepare($sql);
    $st->execute();
}

function MergeProducts()
{
    $myOil = new Oil('0','0'); // aktualny produkt-produkt (nowa wersja)
    $myRow = null; // aktualny produkt-litraz (stara wersja)

    ClearDB();

    $products = GetProducts();
    $amount = count($products);

    for ($i=0; $i<$amount; $i++)
    {
        $myRow = $products[$i];

        $data = explode("-", $myRow['reference']); // rozbicie refa na tablice
        $ref = $data[0];


            if ($myOil->reference != $ref) // jesli ref sie zmienil, to znaczy ze badamy nowy produkt
            {
                $myOil = new Oil($myRow['id_product'], $ref);
$myOil->AddVariant($myRow['id_product'],$myRow['reference'], true);
            } else {
$myOil->AddVariant($myRow['id_product'],$myRow['reference']); // a jesli sie nie zmienil to dodajemy wariant do primary produktu
                // GetCapacity - pobiera z refa dane i na ich podstawie zwraca w postaci stringu pojemnosc
            }

    }
}

MergeProducts();