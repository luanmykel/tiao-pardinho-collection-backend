<?php

namespace Database\Seeders;

use App\Jobs\RefreshSongViews;
use App\Models\Song;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Bus;

class SongSeeder extends Seeder
{
    public function run(): void
    {
        // 36 exemplos
        $songs = [
            ['title' => 'O Mineiro e o Italiano', 'youtube_id' => 's9kVG2ZaTS4', 'plays' => 5200000],
            ['title' => 'Pagode em Brasília', 'youtube_id' => 'lpGGNA6_920', 'plays' => 5000000],
            ['title' => 'Rio de Lágrimas', 'youtube_id' => 'FxXXvPL3JIg', 'plays' => 153000],
            ['title' => 'Tristeza do Jeca', 'youtube_id' => 'tRQ2PWlCcZk', 'plays' => 154000],
            ['title' => 'Terra Roxa', 'youtube_id' => '4Nb89GFu2g4', 'plays' => 3300000],
            ['title' => 'Ditado Sertanejo', 'youtube_id' => 'PnMDX_Ks7eQ', 'plays' => 374000],
            ['title' => 'Rei do Gado', 'youtube_id' => 'YQHcAQaC6EU', 'plays' => 10000],
            ['title' => 'Boi Soberano', 'youtube_id' => '3ZFO_0PFuHI', 'plays' => 10000],
            ['title' => 'Chora Viola', 'youtube_id' => '7ODUHvbqcNs', 'plays' => 10000],
            ['title' => 'Faca Que Não Corta', 'youtube_id' => 'dmzWLsOWMxg', 'plays' => 10000],
            ['title' => 'Rei sem Coroa', 'youtube_id' => 'YpFTAiHpot4', 'plays' => 10000],
            ['title' => 'A Majestade “O Pagode”', 'youtube_id' => '3FKHoB1gtI0', 'plays' => 10000],
            ['title' => 'Boiada Cuiabana', 'youtube_id' => 's_pvnDB_xmw', 'plays' => 10000],
            ['title' => 'Mineiro de Monte Belo', 'youtube_id' => '_NVfHTg8NW0', 'plays' => 10000],
            ['title' => 'Travessia do Araguaia', 'youtube_id' => 'HBido2rDs3c', 'plays' => 10000],
            ['title' => 'Falou e Disse', 'youtube_id' => 'foUUT4AKkz0', 'plays' => 10000],
            ['title' => 'A Coisa T Feia', 'youtube_id' => 'kYyZByIaElE', 'plays' => 10000],
            ['title' => 'Viola Divina', 'youtube_id' => 'zNETUpvtpA8', 'plays' => 10000],
            ['title' => 'Em Tempo de Avanço', 'youtube_id' => 'CByuFIEONTY', 'plays' => 10000],
            ['title' => 'Bandeira Branca', 'youtube_id' => 'x-VqJ1QgQ7k', 'plays' => 10000],
            ['title' => 'Fim da Picada', 'youtube_id' => 'w_5jeRoHvf8', 'plays' => 10000],
            ['title' => 'Amargurado', 'youtube_id' => 'ct9SLUCZ7mw', 'plays' => 10000],
            ['title' => 'Herói sem Medalha', 'youtube_id' => '-A2RG0dNC68', 'plays' => 10000],
            ['title' => 'Oi Paixão', 'youtube_id' => 'lKcmac_ee3c', 'plays' => 10000],
            ['title' => 'Nove e Nove', 'youtube_id' => 'AtvTwuEBk9E', 'plays' => 10000],
            ['title' => 'Uma Coisa Puxa a Outra', 'youtube_id' => '2m8MyK8iNrI', 'plays' => 10000],
            ['title' => 'Catimbau', 'youtube_id' => 'bipS2omIWVk', 'plays' => 10000],
            ['title' => 'A Mão do Tempo', 'youtube_id' => 'VTxVKlxoO4M', 'plays' => 10000],
            ['title' => 'Estrela de Ouro', 'youtube_id' => 'VrvWrkbXfXE', 'plays' => 10000],
            ['title' => 'Minas Gerais', 'youtube_id' => 'pfBycLzvPr4', 'plays' => 10000],
            ['title' => 'Ferreirinha', 'youtube_id' => 'rKB1oMQRr5A', 'plays' => 10000],
            ['title' => 'Amor e Saudade', 'youtube_id' => 'SjLcheCqfwc', 'plays' => 10000],
            ['title' => 'Pai João', 'youtube_id' => 'EB6R2NMxFQ8', 'plays' => 10000],
            ['title' => 'Velho Amor', 'youtube_id' => '3AdQCYw1a6Q', 'plays' => 10000],
            ['title' => 'A Vaca Já Foi pro Brejo', 'youtube_id' => 'kvb-8E5V7ng', 'plays' => 10000],
            ['title' => 'A Casa', 'youtube_id' => 'DmhzCbezX1s', 'plays' => 10000],
        ];

        // atualiza as views pelo youtube
        foreach ($songs as $s) {
            Song::updateOrCreate(
                ['youtube_id' => $s['youtube_id']],
                ['title' => $s['title'], 'plays' => $s['plays']],
            );
        }

        Bus::dispatchSync(new RefreshSongViews);
    }
}
