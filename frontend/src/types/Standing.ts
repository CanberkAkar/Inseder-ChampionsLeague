// src/types/Standing.ts
export interface Standing {
  id: number
  team_id: number
  team_name: string
  short_name: string
  logo_color: string
  logo_url?: string
  played: number
  won: number
  drawn: number
  lost: number
  goals_for: number
  goals_against: number
  goal_difference: number
  points: number
}
