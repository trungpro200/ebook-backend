<?php

return [
    'url' => env('TTS_SERVER_URL', 'http://127.0.0.1:8765'),
    'token' => env('TTS_SHARED_TOKEN', ''),
    'model_version' => env('TTS_MODEL_VERSION', 'kokoro-82m-v1.0'),
    'vietnamese_model_version' => env('TTS_VI_MODEL_VERSION', 'korvatts-0.1.3-e5c8c218-huu-dat'),
];
