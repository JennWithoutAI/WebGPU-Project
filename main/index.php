<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>WebGPU Triangle</title>
    <style>
        body {
            margin: 0;
            background: #ffff;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        canvas {
            width: 800px;
            height: 600px;
            border: 1px solid #444;
        }
    </style>
</head>
<body>
<canvas id="gpuCanvas" width="800" height="600"></canvas>

<script type="module">
    async function init() {
        if (!navigator.gpu) {
            throw new Error("WebGPU not supported in this browser.");
        }

        // Adapter + device
        const adapter = await navigator.gpu.requestAdapter();
        if (!adapter) throw new Error("No GPU adapter found.");

        const device = await adapter.requestDevice();

        // Canvas setup
        const canvas = document.querySelector("#gpuCanvas");
        if (!canvas) throw new Error("Canvas not found");

        const context = canvas.getContext("webgpu");
        const format = navigator.gpu.getPreferredCanvasFormat();

        context.configure({
            device,
            format,
            alphaMode: "premultiplied",
        });

        // Shader (WGSL)
        const shaderCode = await fetch("./shaders/shader.wgsl").then(r => r.text());

        const shaderModule = device.createShaderModule({
            code: shaderCode,
        });

        const res = await fetch("./shaders/config/vertexJson.json");
        const vertexData = await res.json();

        const vertices = new Float32Array(vertexData.triangle);

        const vertexBuffer = device.createBuffer({
            size: vertices.byteLength,
            usage: GPUBufferUsage.VERTEX | GPUBufferUsage.COPY_DST,
        });

        device.queue.writeBuffer(vertexBuffer, 0, vertices);

        const vertexBuffers = [
            {
                arrayStride: 32,
                attributes: [
                    {
                        shaderLocation: 0,
                        offset: 0,
                        format: "float32x4",
                    },
                    {
                        shaderLocation: 1,
                        offset: 16,
                        format: "float32x4",
                    },
                ],
            },
        ];

        // Pipeline
        const pipeline = device.createRenderPipeline({
            layout: "auto",
            vertex: {
                module: shaderModule,
                entryPoint: "vertex_main",
                buffers: vertexBuffers,
            },
            fragment: {
                module: shaderModule,
                entryPoint: "fragment_main",
                targets: [{ format }],
            },
            primitive: {
                topology: "triangle-list",
            },
        });

        // Render
        const commandEncoder = device.createCommandEncoder();

        const textureView = context.getCurrentTexture().createView();

        const renderPass = commandEncoder.beginRenderPass({
            colorAttachments: [
                {
                    view: textureView,
                    clearValue: { r: 0.0, g: 0.5, b: 1.0, a: 1.0 },
                    loadOp: "clear",
                    storeOp: "store",
                },
            ],
        });

        renderPass.setPipeline(pipeline);
        renderPass.setVertexBuffer(0, vertexBuffer);
        renderPass.draw(3);
        renderPass.end();

        device.queue.submit([commandEncoder.finish()]);
    }

    init().catch(err => {
        console.error(err);
        alert(err.message);
    });
</script>
<br><br>
<div>
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
        // hardcoded but will be fixed in future if need diff
        $jsonFileName = "./shaders/config/vertexJson.json";

        if(!file_exists("./".$jsonFileName)){
            newFile($jsonFileName);
        }

        // if config is updated by configScreen
        // note to self this is heavly unsafe due no checks but for now it works
        if(isset($_POST) && isset($_POST["reset"])){
            resetConfig($jsonFileName,$_POST["reset"]);
        }
        if(isset($_POST) && isset($_POST["3Dtype"])){
            updateConfig($jsonFileName,$_POST);
        }

        // errors
        $fileContent = json_decode(file_get_contents($jsonFileName),true);
        if(!$fileContent){
            die("no config content");
        }



        // render configScreen
        configScreen($fileContent);

    }
    function configScreen($data){
        echo "<div class='container'>";
            foreach($data as $configType => $configValues){
                echo "<h2>".$configType."</h2>";
                echo "<form method='post' action='#'>";
                echo "<input hidden name='3Dtype' value='".$configType."'>";
                $count = 0;
                $id = 0;
                $maxCount = 5;
                foreach($configValues as $configValue){
                    $count++;
                    $id++;
                    if($count === $maxCount){
                        echo "<input class='3dConfig' step='0.1' name='".$id."' value='".$configValue."' type='number'/>";
                        $count = 0;
                        continue;
                    }
                    echo "<input class='3dConfig' step='0.1' name='".$id."' value='".$configValue."' type='number'/>";
                }
                echo "<br><input type='submit' value='Set Values'>";
                echo "</form>";
                // reset button
                echo "<form action='#' method='post'>";
                echo "<input hidden name='reset' value='".$configType."'";
                echo "<br><input type='submit' value='RESET - {$configType}'>";

                echo "</form>";
            }
        echo "</div>";
    }
    function resetConfig($file,$typeReset){
        // temp switch

        if($typeReset === "triangle") {
          $resetArr = [
                        0.0,  0.6, 0.0, 1.0,  1.0, 0.0, 0.0, 1.0,
                        -0.5, -0.6, 0.0, 1.0,  0.0, 1.0, 0.0, 1.0,
                        0.5, -0.6, 0.0, 1.0,  0.0, 0.0, 1.0, 1.0,
            ];
        } else {
            return;
        }

        // I SHOULD REALLY CHANGE THIS BUT IT WORKS
        $fileContent = json_decode(file_get_contents($file),true);
        if(!$fileContent){
            die("no config content");
        }
        $fileContent[$typeReset] = $resetArr;
        file_put_contents($file,json_encode($fileContent));
    }
    function updateConfig($file, $originalDataArray){
        $dataKey = $originalDataArray["3Dtype"];

        $newDataArray = [$dataKey => []];

        foreach($_POST as $key => $value){
            if($key === "3Dtype") continue;

            if(is_numeric($key)){
                $newDataArray[$dataKey][] = (float)$value;
            }
        }

        file_put_contents($file, json_encode($newDataArray));
    }
    init();
    die();
    ?>
</div>
</body>
</html>