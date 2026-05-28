<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeliveryPriceService
{
    /**
     * @return array{result: string}
     */
    public function getPriceForPhone(string $phone): array
    {
        $phone = $this->normalizePhone($phone);

        $chatId = $this->resolveChatIdByPhone($phone);
        if ($chatId === null) {
            return ['result' => 'No se encontró el chat para este teléfono.'];
        }

        $location = $this->findLocationInChatMessages($chatId);
        if ($location === null) {
            return ['result' => 'No se encontró la ubicación.'];
        }

        return $this->resolvePriceFromCoordinates(
            $location['latitude'],
            $location['longitude']
        );
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function resolveChatIdByPhone(string $phone): ?string
    {
        $chats = $this->fetchTikketChats();
        $matches = array_values(array_filter($chats, fn (array $chat) => $this->chatMatchesPhone($chat, $phone)));

        if ($matches === []) {
            return null;
        }

        usort($matches, fn (array $a, array $b) => ($b['updated_at'] ?? 0) <=> ($a['updated_at'] ?? 0));

        $id = $matches[0]['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTikketChats(): array
    {
        $channelId = config('delivery.tikket_channel_id');
        $userId = config('delivery.tikket_user_id');
        $baseUrl = rtrim((string) config('delivery.tikket_base_url'), '/');

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'e233356f-5d51-47c6-b97c-881d9c55ec4d',
        ];

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->acceptJson()
            ->get("{$baseUrl}/channel/{$channelId}/tikket", [
                'user_id' => $userId,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'No se pudo obtener los chats de Tikket (HTTP '.$response->status().').'
            );
        }

        $payload = $response->json();
        if (! is_array($payload) || ($payload['error'] ?? false) === true) {
            throw new RuntimeException('Respuesta inválida al consultar chats de Tikket.');
        }

        $data = $payload['data'] ?? null;

        return is_array($data) ? $data : [];
    }

    private function chatMatchesPhone(array $chat, string $phone): bool
    {
        $candidates = [
            $chat['network']['phone'] ?? null,
            $chat['customer']['phone'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            if ($this->normalizePhone($candidate) === $phone) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private function findLocationInChatMessages(string $chatId): ?array
    {
        $messages = $this->fetchTikketMessages($chatId);
        $withLocation = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $location = $message['location'] ?? null;
            if (! is_array($location)) {
                continue;
            }

            $latitude = $location['latitude'] ?? null;
            $longitude = $location['longitude'] ?? null;

            if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                continue;
            }

            $withLocation[] = [
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'created_at' => (int) ($message['created_at'] ?? 0),
            ];
        }

        if ($withLocation === []) {
            return null;
        }

        usort($withLocation, fn (array $a, array $b) => $b['created_at'] <=> $a['created_at']);

        return [
            'latitude' => $withLocation[0]['latitude'],
            'longitude' => $withLocation[0]['longitude'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTikketMessages(string $chatId): array
    {
        $baseUrl = rtrim((string) config('delivery.tikket_base_url'), '/');

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'e233356f-5d51-47c6-b97c-881d9c55ec4d',
        ];

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->acceptJson()
            ->get("{$baseUrl}/tikket/{$chatId}/message");

        if (! $response->successful()) {
            throw new RuntimeException(
                'No se pudo obtener los mensajes del chat (HTTP '.$response->status().').'
            );
        }

        $payload = $response->json();
        if (! is_array($payload) || ($payload['error'] ?? false) === true) {
            throw new RuntimeException('Respuesta inválida al consultar mensajes de Tikket.');
        }

        $data = $payload['data'] ?? null;

        return is_array($data) ? $data : [];
    }

    /**
     * @return array{result: string}
     */
    private function resolvePriceFromCoordinates(float $latitude, float $longitude): array
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->get((string) config('delivery.location_api_url'), [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'No se pudo calcular el precio de delivery (HTTP '.$response->status().').'
            );
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Respuesta inválida del servicio de ubicación.');
        }

        if (($payload['available'] ?? false) === true) {
            $total = $payload['total'] ?? null;

            return ['result' => (string) $total];
        }

        return ['result' => 'Zona fuera de cobertura.'];
    }
}
