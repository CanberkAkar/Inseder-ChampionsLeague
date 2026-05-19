<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Standing modeli — her takımın lig istatistiklerini tutar.
 *
 * Tasarım kararı: standings tablosu maç tablosundan türetilen bir "özet" tablo.
 * Teorik olarak her seferinde maçlardan hesaplanabilir ama bu çok maliyetli;
 * bunun yerine her maç oynandıkça artımlı güncelliyoruz.
 *
 * Tüm sayısal alanlar integer cast'li çünkü MySQL'den string olarak gelebiliyorlar.
 * Cast olmasa PHP tarafında "3" + 1 = "31" gibi string birleştirme sürprizleri çıkardı.
 *
 * goal_difference bir accessor, ayrı kolon değil.
 * Hem goals_for hem goals_against zaten var; ayrı tutmak tutarsızlığa kapı aralar.
 */
class Standing extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'played',
        'won',
        'drawn',
        'lost',
        'goals_for',
        'goals_against',
        'points',
    ];

    protected $casts = [
        'played'        => 'integer',
        'won'           => 'integer',
        'drawn'         => 'integer',
        'lost'          => 'integer',
        'goals_for'     => 'integer',
        'goals_against' => 'integer',
        'points'        => 'integer',
    ];

    /**
     * Bu kaydın ait olduğu takım.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Gol farkı accessor'ı.
     *
     * $standing->goal_difference şeklinde kullanılabilir.
     * Sıralama sorgusunda raw SQL (CAST kullanarak) ile yapılıyor;
     * bu accessor daha çok model düzeyinde erişim için.
     */
    public function getGoalDifferenceAttribute(): int
    {
        return $this->goals_for - $this->goals_against;
    }
}
