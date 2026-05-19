<?php

namespace App\DTOs;

/**
 * MatchResultDTO — bir maç simülasyonunun sonucunu taşıyan veri transfer nesnesi.
 *
 * Neden DTO?
 * SimulationService::simulateMatch() sadece iki integer döndürüyor aslında,
 * ama bunları bir dizi olarak döndürsek "hangi indeks hangisi?" belirsizliği çıkardı.
 * İki ayrı parametre geçsek metod imzası çirkinleşirdi.
 * DTO ile tip güvenliği ve okunurluk bir arada sağlanıyor.
 *
 * readonly constructor-promoted properties (PHP 8.1+):
 * Nesne bir kez oluşturuldu mu değerleri değiştirilemiyor.
 * Maç sonucu "immutable" olmalı — sonradan homeGoals++  diyemezsin.
 *
 * final class: Bu sınıfın extend edilmesi anlamsız; final yaparak
 * istemeden yapılacak kalıtımın önüne geçiyoruz.
 */
final class MatchResultDTO
{
    public function __construct(
        public readonly int $homeGoals,
        public readonly int $awayGoals,
    ) {}

    /**
     * Kazananı string olarak döner.
     *
     * PHP 8 match expression kullandım; switch'e göre daha temiz,
     * strict comparison yapıyor ve fall-through riski yok.
     *
     * @return string 'home' | 'away' | 'draw'
     */
    public function getWinner(): string
    {
        return match (true) {
            $this->homeGoals > $this->awayGoals => 'home',
            $this->homeGoals < $this->awayGoals => 'away',
            default                             => 'draw',
        };
    }

    /**
     * DTO'yu düz dizi olarak döner.
     * Loglama veya debugging sırasında kullanışlı.
     */
    public function toArray(): array
    {
        return [
            'home_goals' => $this->homeGoals,
            'away_goals' => $this->awayGoals,
            'winner'     => $this->getWinner(),
        ];
    }
}
