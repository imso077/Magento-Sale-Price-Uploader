<?php
/*
 * This script was created for a dedicated purpose for a predefined store. YOU NEED TO CUSTOMIZE IT TO YOUR NEEDS IF USING!!!
 *
 *
 * */

require_once('../app/Mage.php');
Mage::app('admin');
$file = "sale.csv"; /* Required format for csv is beside this file, called sale.csv.... */
if ($file == null):
    echo "No file exiting now.";
    die;
endif;
/* if called with "php -f sale.php banner" it will replace the banner on running .*/
$sitepat = $argv[1];
if ($sitepat==null) {
    $changebanner=false;
} else {
    $changebanner=$sitepat;
}

Mage::app()->getStore()->setId(Mage_Core_Model_App::ADMIN_STORE_ID);
Mage::register("isSecureArea", true);

function getEntityID_bySKU($db_magento, $sku) {
    try {
    $entity_row = $db_magento->query("SELECT entity_id FROM catalog_product_entity p_e WHERE p_e.sku = '$sku'")->fetchObject();
    if ($entity_row):$entity_id = $entity_row->entity_id;
    else:
        $entity_id = false;
    endif;
    return $entity_id;  } catch (Exception $e) {
            Mage::log("[ERROR] Entity probelm MAGENTO ERROR " . $e->getMessage(), null, "sale.log", true);
return false;
        }
}

function getEntityID_byean($db_magento, $ean) {
    $entity_id=Mage::getModel("catalog/product")->loadByAttribute("ean",$ean);
   
    if ($entity_id):
        
       return $entity_id->getId();
    else:
        return false;
    endif;
   
}

function reindex() {
    for ($i = 1; $i <= 15; $i++) {
        try {
            $process = Mage::getModel('index/process')->load($i);
            $process->reindexAll();
        } catch (Exception $e) {
            Mage::log("[ERROR] INDEX MAGENTO ERROR " . $e->getMessage(), null, "sale.log", true);
        }
    }
}


$mage_csv = new Varien_File_Csv();
$csvdata = $mage_csv->getData($file);
$modifiedarray = array();
$configpricearray=array();
$coreResource = Mage::getSingleton('core/resource');
$connect = $coreResource->getConnection('core_write');
$missing=array();
$missingskus=array();


Mage::log("START UPDATE", null, "sale.log", true);


foreach ($csvdata as $data) :

    $entity_id = getEntityID_bySKU($connect, $data[0]);
    $saleprice = $data[1];
    $storeId = $data[2];
    $categorypromo = $data[3];
    $productpromo = $data[4];
    $cmspromo = $data[5];

    if (!$entity_id):
        $missing[]=$data[0]; 
        $entity_id2 = getEntityID_byean($connect, $data[0]);
        if (!$entity_id2):
            $missingskus[]=$data[0];
        endif;
        $entity_id=$entity_id2;
    endif;
    
   // $entity_id=false;
    if ($entity_id):
        /* Update the Sale Price */
        if ($saleprice) {
            try {
                $connect->query("REPLACE INTO catalog_product_entity_decimal (entity_type_id, attribute_id, store_id, entity_id, value) VALUES(4,76,$storeId,$entity_id,$saleprice)");
                Mage::log("\t\t\tUPDATE MAGENTO SUCCESS Sku: " . $data[0] . " Sale price:" . $saleprice . " Entity id: " . $entity_id . " Store id: " . $storeId, null, "sale.log", true);
            } catch (Exception $e) {

                Mage::log("\t\t\t[ERROR] UPDATE MAGENTO ERROR Sku: " . $data[0] . " Cost:" . $saleprice . "error: " . $e->getMessage(), null, "sale.log", true);
            }
        }
        /* Update the list Promo */
        if ($categorypromo) {
            try {
                $connect->query("REPLACE INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES(4,527,$storeId,$entity_id,'$categorypromo')");
                Mage::log("\t\t\t\tUPDATE MAGENTO CATEGORY PROMO SUCCESS Sku: " . $data[0] . " Promo Code:" . $categorypromo . " Entity id: " . $entity_id . " Store id: " . $storeId, null, "sale.log", true);
            } catch (Exception $e) {

                Mage::log("\t\t\t\t[ERROR] UPDATE CATEGORY PROMO MAGENTO ERROR Sku: " . $data[0] . " Promo Code:" . $categorypromo . "error: " . $e->getMessage(), null, "sale.log", true);
            }
        }
        /* Update the Product Promo */
        if ($productpromo) {
            try {
                $connect->query("REPLACE INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES(4,526,$storeId,$entity_id,'$productpromo')");
                Mage::log("\t\t\t\tUPDATE MAGENTO PRODUCT PROMO SUCCESS Sku: " . $data[0] . " Promo Code:" . $productpromo . " Entity id: " . $entity_id . " Store id: " . $storeId, null, "sale.log", true);
            } catch (Exception $e) {

                Mage::log("\t\t\t\t[ERROR] UPDATE PRODUCT PROMO MAGENTO ERROR Sku: " . $data[0] . " Promo Code:" . $productpromo . "error: " . $e->getMessage(), null, "sale.log", true);
            }
        }

        /* Update the Promo on CMS pages */
        if ($cmspromo) {
            try {
                $connect->query("REPLACE INTO catalog_product_entity_int (entity_type_id, attribute_id, store_id, entity_id, value) VALUES(4,230,$storeId,$entity_id,'$cmspromo')");
                Mage::log("\t\t\t\tUPDATE MAGENTO PRODUCT cMS PROMO SUCCESS Sku: " . $data[0] . " Promo Code:" . $cmspromo . " Entity id: " . $entity_id . " Store id: " . $storeId, null, "sale.log", true);
            } catch (Exception $e) {

                Mage::log("\t\t\t\t[ERROR] UPDATE PRODUCT Cms PROMO MAGENTO ERROR Sku: " . $data[0] . " Promo Code:" . $cmspromo . "error: " . $e->getMessage(), null, "sale.log", true);
            }
        }
    endif;

endforeach;
if ($changebanner) {
    Mage::log("\t Starting CMS changes", null, "sale.log", true);
    try {
        $timed=Mage::getModel('cms/block')->load('homepage_banner_timed')->getId();
        $normal=Mage::getModel('cms/block')->load('homepage_banner')->getId();
        $connect->query("Update cms_block SET identifier=\"homepage_banner_tmp\" WHERE block_id=$normal;");
        $connect->query("Update cms_block SET identifier=\"homepage_banner\" WHERE block_id=$timed;");
        $connect->query("Update cms_block SET identifier=\"homepage_banner_timed\" WHERE block_id=$normal;");
    }
    catch (Exception $e) {

        Mage::log("\t\t\t[ERROR] UPDATE MAGENTO CMS error: " . $e->getMessage(), null, "sale.log", true);
    }
    Mage::log("\t Finished CMS changes", null, "sale.log", true);
}

Mage::log("\t Starting REINDEX", null, "sale.log", true);
reindex();
Mage::log("\t Finishing REINDEX", null, "sale.log", true);

try {
    Mage::app()->cleanCache();
    Mage::log("\t\t Cache cleared: ", null, "sale.log", true);
} catch (Exception $e) {
    Mage::log("\t\t\t[ERROR] CACHE CLEAN error: " . $e->getMessage(), null, "sale.log", true);
}

Mage::log("FINISHED UPDATE", null, "sale.log", true);
