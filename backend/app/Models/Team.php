<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Team modeli — takım bilgilerini tutar.
 *
 * power alanı simülasyonun kalbi: 1-100 arası bir güç puanı.
 * Gerçek kulüplerin görece güçleri düşünülerek belirlendi:
 * Manchester City 92, Real Madrid 90, Bayern 88, PSG 85.
 *
 * logo_color hex kodu olarak tutuluyor (#6CABDD gibi).
 * Gerçek logo yerine renkli bir daire + kısa ad gösteriyoruz;
 * bu sayede lisans sorunu olmadan takım kimliği sağlanıyor.
 */
class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name', // 3 harfli kısa ad, tablo ve kart görünümü için
        'power',
        'logo_color',
        'logo_url',
    ];

    protected $casts = [
        'power' => 'integer',
    ];

    /**
     * Takımın ev sahibi olduğu maçlar.
     * Foreign key açıkça yazılmasa da Eloquent 'home_team_id' bulamazdı;
     * 'team_id' varsayılanla arayıp hata verirdi.
     */
    public function homeMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'home_team_id');
    }

    /**
     * Takımın deplasman olduğu maçlar.
     */
    public function awayMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'away_team_id');
    }

    /**
     * Takımın puan tablosundaki kaydı.
     * Her takımın tek bir standing kaydı var (hasOne).
     */
    public function standing(): HasOne
    {
        return $this->hasOne(Standing::class);
    }
}
