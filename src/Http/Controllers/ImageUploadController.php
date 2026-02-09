<?php

namespace Microblink\IdImageUpload\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Microblink\IdImageUpload\Exceptions\ApiException;
use Microblink\IdImageUpload\Exceptions\ImageUploadException;
use Microblink\IdImageUpload\Exceptions\InvalidImageException;
use Microblink\IdImageUpload\Facades\MicroblinkUploader;

class ImageUploadController extends Controller
{
    /**
     * Upload a single ID/document image.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|image|max:10240', // 10MB max
        ]);

        try {
            $response = MicroblinkUploader::upload(
                $request->file('image')
            );

            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        } catch (InvalidImageException $e) {
            return response()->json([
                'success' => false,
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (ApiException $e) {
            return response()->json([
                'success' => false,
                'error' => 'api_error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        } catch (ImageUploadException $e) {
            return response()->json([
                'success' => false,
                'error' => 'upload_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload front and back images for two-sided documents.
     */
    public function uploadMultiSide(Request $request): JsonResponse
    {
        $request->validate([
            'front_image' => 'required|file|image|max:10240',
            'back_image' => 'required|file|image|max:10240',
        ]);

        try {
            $response = MicroblinkUploader::uploadMultiSide(
                $request->file('front_image'),
                $request->file('back_image')
            );

            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        } catch (InvalidImageException $e) {
            return response()->json([
                'success' => false,
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (ApiException $e) {
            return response()->json([
                'success' => false,
                'error' => 'api_error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        } catch (ImageUploadException $e) {
            return response()->json([
                'success' => false,
                'error' => 'upload_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a base64 encoded image.
     */
    public function uploadBase64(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|string',
        ]);

        try {
            $response = MicroblinkUploader::uploadBase64(
                $request->input('image')
            );

            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        } catch (ApiException $e) {
            return response()->json([
                'success' => false,
                'error' => 'api_error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        } catch (ImageUploadException $e) {
            return response()->json([
                'success' => false,
                'error' => 'upload_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload front and back base64 encoded images.
     */
    public function uploadMultiSideBase64(Request $request): JsonResponse
    {
        $request->validate([
            'front_image' => 'required|string',
            'back_image' => 'required|string',
        ]);

        try {
            $response = MicroblinkUploader::uploadMultiSideBase64(
                $request->input('front_image'),
                $request->input('back_image')
            );

            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        } catch (ApiException $e) {
            return response()->json([
                'success' => false,
                'error' => 'api_error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        } catch (ImageUploadException $e) {
            return response()->json([
                'success' => false,
                'error' => 'upload_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
