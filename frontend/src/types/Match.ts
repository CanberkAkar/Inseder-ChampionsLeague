// src/types/Match.ts
export type MatchResult = 'home_win' | 'away_win' | 'draw' | null

export interface Match {
  id: number
  week: number
  home_team_id: number
  home_team_name: string
  home_short_name: string
  home_logo_color: string
  home_logo_url?: string
  away_team_id: number
  away_team_name: string
  away_short_name: string
  away_logo_color: string
  away_logo_url?: string
  home_goals: number | null
  away_goals: number | null
  is_played: boolean
  result: MatchResult
}

export interface MatchesByWeek {
  [week: number]: Match[]
}
