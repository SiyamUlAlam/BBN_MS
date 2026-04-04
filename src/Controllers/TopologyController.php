<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\TopologyRepository;

final class TopologyController
{
    public function __construct(private readonly TopologyRepository $topology = new TopologyRepository())
    {
    }

    public function listZones(Request $request): void
    {
        Response::json(['status' => 'success', 'data' => $this->topology->listZones()]);
    }

    public function createZone(Request $request): void
    {
        if (!is_array($request->body) || empty($request->body['name'])) {
            Response::json(['status' => 'error', 'message' => 'name is required'], 422);
            return;
        }

        $zone = $this->topology->createZone($request->body);
        Response::json(['status' => 'success', 'data' => $zone], 201);
    }

        public function updateZone(Request $request): void
        {
            $id = trim((string) $request->input('id', ''));
            if ($id === '') {
                Response::json(['message' => 'Zone id is required'], 422);
                return;
            }

            $updated = $this->topology->updateZone($id, [
                'name' => $request->input('name'),
                'code' => $request->input('code'),
            ]);

            if (!$updated) {
                Response::json(['message' => 'No changes made or zone not found'], 404);
                return;
            }

            Response::json(['message' => 'Zone updated']);
        }

        public function deleteZone(Request $request): void
        {
            $id = trim((string) $request->input('id', ''));
            if ($id === '') {
                Response::json(['message' => 'Zone id is required'], 422);
                return;
            }

            $deleted = $this->topology->deleteZone($id);
            if (!$deleted) {
                Response::json(['message' => 'Zone not found'], 404);
                return;
            }

            Response::json(['message' => 'Zone deleted']);
        }

    public function listAreas(Request $request): void
    {
        $zoneId = (string) ($request->query['zone_id'] ?? '');
        if ($zoneId === '') {
            Response::json(['status' => 'error', 'message' => 'zone_id query param is required'], 422);
            return;
        }

        Response::json(['status' => 'success', 'data' => $this->topology->listAreasByZone($zoneId)]);
    }

    public function createArea(Request $request): void
    {
        if (!is_array($request->body) || empty($request->body['name']) || empty($request->body['zone_id'])) {
            Response::json(['status' => 'error', 'message' => 'zone_id and name are required'], 422);
            return;
        }

        $area = $this->topology->createArea($request->body);
        Response::json(['status' => 'success', 'data' => $area], 201);
    }

        public function updateArea(Request $request): void
        {
            $id = trim((string) $request->input('id', ''));
            if ($id === '') {
                Response::json(['message' => 'Area id is required'], 422);
                return;
            }

            $updated = $this->topology->updateArea($id, [
                'zone_id' => $request->input('zone_id'),
                'name' => $request->input('name'),
                'code' => $request->input('code'),
            ]);

            if (!$updated) {
                Response::json(['message' => 'No changes made or area not found'], 404);
                return;
            }

            Response::json(['message' => 'Area updated']);
        }

        public function deleteArea(Request $request): void
        {
            $id = trim((string) $request->input('id', ''));
            if ($id === '') {
                Response::json(['message' => 'Area id is required'], 422);
                return;
            }

            $deleted = $this->topology->deleteArea($id);
            if (!$deleted) {
                Response::json(['message' => 'Area not found'], 404);
                return;
            }

            Response::json(['message' => 'Area deleted']);
        }

    public function listLineSources(Request $request): void
    {
        Response::json(['status' => 'success', 'data' => $this->topology->listLineSources()]);
    }

    public function createLineSource(Request $request): void
    {
        if (!is_array($request->body) || empty($request->body['name'])) {
            Response::json(['status' => 'error', 'message' => 'name is required'], 422);
            return;
        }

        $source = $this->topology->createLineSource($request->body);
        Response::json(['status' => 'success', 'data' => $source], 201);
    }

        public function updateLineSource(Request $request): void
        {
            $id = trim((string) $request->input('id', ''));
            if ($id === '') {
                Response::json(['message' => 'Line source id is required'], 422);
                return;
            }

            $updated = $this->topology->updateLineSource($id, [
                'name' => $request->input('name'),
                'provider' => $request->input('provider'),
                'capacity_mbps' => $request->input('capacity_mbps'),
            ]);

            if (!$updated) {
                Response::json(['message' => 'No changes made or line source not found'], 404);
                return;
            }

            Response::json(['message' => 'Line source updated']);
        }

        public function deleteLineSource(Request $request): void
        {
            $id = trim((string) $request->input('id', ''));
            if ($id === '') {
                Response::json(['message' => 'Line source id is required'], 422);
                return;
            }

            $deleted = $this->topology->deleteLineSource($id);
            if (!$deleted) {
                Response::json(['message' => 'Line source not found'], 404);
                return;
            }

            Response::json(['message' => 'Line source deleted']);
        }

    public function listDistributionBoxes(Request $request): void
    {
        $zoneId = (string) ($request->query['zone_id'] ?? '');
        Response::json(['status' => 'success', 'data' => $this->topology->listDistributionBoxes($zoneId)]);
    }

    public function createDistributionBox(Request $request): void
    {
        if (!is_array($request->body) || empty($request->body['name'])) {
            Response::json(['status' => 'error', 'message' => 'name is required'], 422);
            return;
        }

        $box = $this->topology->createDistributionBox($request->body);
        Response::json(['status' => 'success', 'data' => $box], 201);
    }

        public function updateDistributionBox(Request $request): void
        {
            $id = trim((string) $request->input('id', ''));
            if ($id === '') {
                Response::json(['message' => 'Distribution box id is required'], 422);
                return;
            }

            $updated = $this->topology->updateDistributionBox($id, [
                'zone_id' => $request->input('zone_id'),
                'area_id' => $request->input('area_id'),
                'line_source_id' => $request->input('line_source_id'),
                'name' => $request->input('name'),
                'code' => $request->input('code'),
                'capacity_ports' => $request->input('capacity_ports'),
                'used_ports' => $request->input('used_ports'),
                'status' => $request->input('status'),
            ]);

            if (!$updated) {
                Response::json(['message' => 'No changes made or distribution box not found'], 404);
                return;
            }

            Response::json(['message' => 'Distribution box updated']);
        }

        public function deleteDistributionBox(Request $request): void
        {
            $id = trim((string) $request->input('id', ''));
            if ($id === '') {
                Response::json(['message' => 'Distribution box id is required'], 422);
                return;
            }

            $deleted = $this->topology->deleteDistributionBox($id);
            if (!$deleted) {
                Response::json(['message' => 'Distribution box not found'], 404);
                return;
            }

            Response::json(['message' => 'Distribution box deleted']);
        }
}
