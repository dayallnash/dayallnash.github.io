<?php

namespace App\Controller;

use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DefaultController extends AbstractController
{
    public function index(): Response
    {
        $client = HttpClient::create();

        try {
            $response = $client->request('GET', 'https://admiraltyapi.azure-api.net/uktidalapi/api/V1/Stations', [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $_SERVER['PRIMARY_KEY'],
                ]
            ]);

            $responseArray = $response->toArray();

            $stations = [];
            foreach ($responseArray['features'] as $station) {
                if ($station['properties']['Country'] === 'England' && (((int) ceil($station['geometry']['coordinates'][0]) === -4 || (int) ceil($station['geometry']['coordinates'][0]) === -3 || (int) ceil($station['geometry']['coordinates'][0]) === -5) && (int) floor($station['geometry']['coordinates'][1]) === 50)) {
                    $station['properties']['Name'] = ucwords(strtolower($station['properties']['Name']));
                    $stations[] = $station;
                }
            }
        } catch (Throwable $t) {
            dump($t->getMessage());
        }

        return $this->render('default/index.html.twig', ['stations' => $stations ?? []]);
    }

    public function tides(Request $request): Response
    {
        if (empty($request->get('id'))) {
            return new Response(null, 400);
        }

        $client = HttpClient::create();

        try {
            $response = $client->request(
                'GET',
                'https://admiraltyapi.azure-api.net/uktidalapi/api/V1/Stations/' . $request->get('id') . '/TidalEvents',
                ['headers' => ['Ocp-Apim-Subscription-Key' => $_SERVER['PRIMARY_KEY']]]
            );

            $info = $response->toArray();

            $now = new DateTime();

            foreach ($info as $key => &$row) {
                foreach ($row as $col => &$data) {
                    if ($data === 'LowWater') {
                        $data = 'Low Water';

                        continue;
                    }

                    if ($data === 'HighWater') {
                        $data = 'High Water';

                        continue;
                    }

                    if ($col === 'DateTime') {
                        $dt = new DateTime($data);

                        $row['previous'] = false;
                        if ($dt < $now) {
                            $row['previous'] = true;

                            unset($info[$key - 1]);
                        }

                        $row['next'] = !empty($info[$key - 1]['previous']);

                        $data = $dt->format('Y-m-d H:i:s');

                        continue;
                    }

                    if ($col === 'Height') {
                        $data = round($data, 2);
                    }
                }
                unset($data);
            }
            unset($row);
        } catch (Throwable $t) {
            dump($t->getMessage());
        }

        return $this->render('default/tides.html.twig', ['info' => $info ?? [], 'name' => $request->get('name')]);
    }
}