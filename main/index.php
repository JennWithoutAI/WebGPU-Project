<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>WebGPU Compute Test</title>
    <style>
        body {
            margin: 0;
            background: #fff;
            display: flex;
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

        // Canvas (not used yet, but kept)
        const canvas = document.querySelector("#gpuCanvas");
        const context = canvas.getContext("webgpu");
        const format = navigator.gpu.getPreferredCanvasFormat();

        context.configure({
            device,
            format,
            alphaMode: "premultiplied",
        });

        // ===== COMPUTE SETUP =====
        const NUM_ELEMENTS = 1000;
        const BUFFER_SIZE = NUM_ELEMENTS * 4;

        const shaderCompile = `
            @group(0) @binding(0)
            var<storage, read_write> output: array<f32>;

            @compute @workgroup_size(64)
            fn main(
                @builtin(global_invocation_id) global_id : vec3u,
                @builtin(local_invocation_id) local_id : vec3u,
            ) {
                if (global_id.x >= ${NUM_ELEMENTS}) {
                    return;
                }

                output[global_id.x] =
                    f32(global_id.x) * 1 + f32(local_id.x);
            }
    `;

        const shaderModule = device.createShaderModule({
            code: shaderCompile,
        });

        const output = device.createBuffer({
            size: BUFFER_SIZE,
            usage: GPUBufferUsage.STORAGE | GPUBufferUsage.COPY_SRC,
        });

        const stagingBuffer = device.createBuffer({
            size: BUFFER_SIZE,
            usage: GPUBufferUsage.MAP_READ | GPUBufferUsage.COPY_DST,
        });

        const bindGroupLayout = device.createBindGroupLayout({
            entries: [
                {
                    binding: 0,
                    visibility: GPUShaderStage.COMPUTE,
                    buffer: { type: "storage" },
                },
            ],
        });

        const bindGroup = device.createBindGroup({
            layout: bindGroupLayout,
            entries: [
                {
                    binding: 0,
                    resource: { buffer: output },
                },
            ],
        });

        const computePipeline = device.createComputePipeline({
            layout: device.createPipelineLayout({
                bindGroupLayouts: [bindGroupLayout],
            }),
            compute: {
                module: shaderModule,
                entryPoint: "main",
            },
        });

        const commandEncoder = device.createCommandEncoder();
        const passEncoder = commandEncoder.beginComputePass();

        passEncoder.setPipeline(computePipeline);
        passEncoder.setBindGroup(0, bindGroup);
        passEncoder.dispatchWorkgroups(Math.ceil(NUM_ELEMENTS / 64));
        passEncoder.end();

        // Copy GPU → CPU buffer
        commandEncoder.copyBufferToBuffer(
            output,
            0,
            stagingBuffer,
            0,
            BUFFER_SIZE
        );

        // Submit work
        device.queue.submit([commandEncoder.finish()]);

        // Read result
        await stagingBuffer.mapAsync(GPUMapMode.READ);

        const copyArrayBuffer = stagingBuffer.getMappedRange();
        const data = copyArrayBuffer.slice();
        stagingBuffer.unmap();

        console.log("Result:", new Float32Array(data));


        // ========== SEP 3 — COMPUTE SHADER ==========
        const shaderTriangle = await fetch("./shaders/triangleShader.wgsl").then(r => r.text());
        const shaderModuleTriangle = device.createShaderModule({
            code: shaderTriangle,
        });

        const sampleSize = 24;

        const copy = [...new Float32Array(data)];

        for (let i = copy.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [copy[i], copy[j]] = [copy[j], copy[i]];
        }

        const sampled = copy.slice(0, sampleSize);

        const vertices = new Float32Array(sampled);

        // ========== SEP 4 — BUFFERS ==========
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

        // ========== SEP 5 — BIND GROUP ==========

        // ========== SEP 6 — PIPELINE ==========
        const pipeline = device.createRenderPipeline({
            layout: "auto",
            vertex: {
                module: shaderModuleTriangle,
                entryPoint: "vertex_main",
                buffers: vertexBuffers,
            },
            fragment: {
                module: shaderModuleTriangle,
                entryPoint: "fragment_main",
                targets: [{ format }],
            },
            primitive: {
                topology: "triangle-list",
            },
        });

        // ========== SEP 7 — ENCODE + DISPATCH ==========
        // Render
        const commandEncoderTriangle = device.createCommandEncoder();

        const textureView = context.getCurrentTexture().createView();

        const renderPass = commandEncoderTriangle.beginRenderPass({
            colorAttachments: [
                {
                    view: textureView,
                    clearValue: { r: 0.0, g: 0.5, b: 1.0, a: 1.0 },
                    loadOp: "clear",
                    storeOp: "store",
                },
            ],
        });
        // ========== SEP 8 — READ BACK CPU ==========
        renderPass.setPipeline(pipeline);
        renderPass.setVertexBuffer(0, vertexBuffer);
        renderPass.draw(3);
        renderPass.end();

        device.queue.submit([commandEncoderTriangle.finish()]);
    }

    /* think points :
    // ========== SEP 1 — GPU INIT ==========

// ========== SEP 2 — CANVAS SETUP ==========

// ========== SEP 3 — COMPUTE SHADER ==========

// ========== SEP 4 — BUFFERS ==========

// ========== SEP 5 — BIND GROUP ==========

// ========== SEP 6 — PIPELINE ==========

// ========== SEP 7 — ENCODE + DISPATCH ==========

// ========== SEP 8 — READ BACK CPU ==========
     */
    init().catch(err => {
        console.error(err);
        alert(err.message);
    });
</script>

</body>
</html>