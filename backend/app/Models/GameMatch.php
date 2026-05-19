<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GameMatch modeli — veritabanındaki "matches" tablosunu temsil eder.
 *
 * Neden "GameMatch"? PHP'de "Match" ayrılmış bir anahtar kelime olduğu için
 * sınıf adı olarak kullanılamıyor. En yakın ve açıklayıcı alternatif bu.
 *
 * is_played bayrağı hem simülasyon hem de düzenleme akışında kritik;
 * false iken maça dokunulmuyor, true olduktan sonra sonuç düzenlenebilir.
 *
 * result alanı bir accessor — veritabanında tutulmuyor, anlık hesaplanıyor.
 * Sebebi: home_goals ve away_goals zaten var, tekrar bir "result" kolonu
 * eklemek redundant olur ve güncelleme sırasında tutarsızlık riski yaratır.
 */
class GameMatch extends Model
{
    use HasFactory;

    // Tablo adı PHP class adından otomatik türetilemiyor (GameMatch → game_matches olurdu)
    protected $table = 'matches';

    protected $fillable = [
        'week',
        'home_team_id',
        'away_team_id',
        'home_goals',
        'away_goals',
        'is_played',
    ];

    protected $casts = [
        'week'       => 'integer',
        'home_goals' => 'integer',
        'away_goals' => 'integer',
        'is_played'  => 'boolean', // DB'de tinyint(1), PHP'de true/false
    ];

    /**
     * Ev sahibi takım ilişkisi.
     * Foreign key açıkça belirtilmeli; Eloquent varsayılan olarak "team_id" arar.
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * Deplasman takımı ilişkisi.
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * Maç sonucunu hesaplayan accessor.
     *
     * $match->result şeklinde erişildiğinde otomatik çalışır.
     * Oynanmamış maçlarda null dönüyor; frontend "VS" gösteriyor.
     * Değerler: 'home_win' | 'away_win' | 'draw' | null
     */
    public function getResultAttribute(): ?string
    {
        if (!$this->is_played) {
            return null;
        }

        return match (true) {
            $this->home_goals > $this->away_goals => 'home_win',
            $this->home_goals < $this->away_goals => 'away_win',
            default                               => 'draw',
        };
    }
}
