<?PHP
function newFile($fileDir,$typeStartFile = false){
    // starter
    if($typeStartFile === false){
        $starterData = ["triangle" => [
            0.0,  0.6, 0.0, 1.0,  1.0, 0.0, 0.0, 1.0,
            -0.5, -0.6, 0.0, 1.0,  0.0, 1.0, 0.0, 1.0,
            0.5, -0.6, 0.0, 1.0,  0.0, 0.0, 1.0, 1.0,
        ]];
        file_put_contents($fileDir,json_encode($starterData));
        return;
    }
    die("Wrong Starter Option chosen!!");
}

function init(){
    $jsonFileName = "./shaders/config/vertexJson.json";

    if(!file_exists("./".$jsonFileName)){
        newFile($jsonFileName);
    }

    $fileContent = json_decode(file_get_contents($jsonFileName),true);
    if(!$fileContent){
        die("no config content");
    }
    configScreen($fileContent);

}
function configScreen($data){
    echo "<div class='container'>";
    foreach($data as $configType => $configValues){
        echo "<h2>".$configType."</h2>";
        // values
        $count = 0;
        $id = 0;
        $maxCount = 5;
        foreach($configValues as $configValue){
            $count++;
            $id++;
            if($count === $maxCount){
                echo "<input name='".$id."' value='".$configValue."' type='number'/>";
                echo "<br>";
                $count = 0;
            }
        }
    }
    echo "</div>";

}
init();
die();
?>