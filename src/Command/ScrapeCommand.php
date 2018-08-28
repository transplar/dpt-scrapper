<?php

namespace App\Command;

use App\Entity\Pemilih;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ScrapeCommand extends Command
{
    private $baseURI = 'https://infopemilu.kpu.go.id/pilkada2018/pemilih/dpt/1/BANTEN/';
    private $suffix = 'listDps.json';
    private $timestamp;
    private $em;
    private $provinsi;
    private $kota;
    private $kecamatan;
    private $kelurahan;
    private $tps;
    protected static $defaultName = 'app:scrape';

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();

        $this->em = $em;
    }

    protected function configure()
    {
        $this
            ->setDescription('Scrap daftar pemilih tetap pilkada tahun 2018 di Provinsi Banten.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Memulai scrapping data ...');
        $this->makeRequest();
        $io->success('Proses scrapping telah selesai.');
    }

    private function generatePath(string $path)
    {
        $this->timestamp = '?_=' . (new \Datetime)->getTimestamp();
        return $path . $this->suffix . $this->timestamp;
    }

    private function makeRequest()
    {
        $path = '';
        $contents = $this->scrap($path);
        $arrayKota = json_decode($contents, true);

        $totalPemilih = 0;
        foreach ($arrayKota['aaData'] as $kota) {
            $totalPemilih += $kota['totalPemilih'];

            // Scrap data by kota
            $pathKota = $path . $kota['namaKabKota'] . '/';
            var_dump($this->scrap($pathKota));
        }
        print "Total Pemilih: " . $totalPemilih . PHP_EOL;

        if (!isset($arrayContents['aaData'][0]['nik'])) {
            print "Not Found!\n";
            return;
        }

        $this->savePemilih($response->getBody()->getContents());
        return;
    }

    private function savePemilih(string $json)
    {
        $arrayPemilih = json_decode($json, true);
        $index = 0;
        foreach ($arrayPemilih['aaData'] as $calonPemilih) {
            if (!isset($calonPemilih['nik'])) {
                print "Nothing to do.\n";
                break;
            }
            $index++;
            $pemilih = new Pemilih;
            $pemilih->setNama($calonPemilih['nama'])
                ->setNik($calonPemilih['nik'])
                ->setJenisKelamin($calonPemilih['jenisKelamin'])
                ->setAlamat('')
                ->setKelurahan('')
                ->setKecamatan('')
                ->setKota('')
                ->setProvinsi('')
            ;
            $this->em->persist($pemilih);
            if (($index % 20) === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }
        $this->em->flush();
        $this->em->clear();
    }

    private function scrap($path)
    {
        $client = new Client(['base_uri' => $this->baseURI]);
        $response = $client->request('GET', $this->generatePath($path));
        return $response->getBody()->getContents();
    }
}
