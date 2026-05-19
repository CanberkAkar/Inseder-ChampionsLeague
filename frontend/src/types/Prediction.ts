// src/types/Prediction.ts
export interface Prediction {
  team_id: number
  team_name: string
  short_name: string
  logo_color: string
  logo_url?: string
  probability: number
}
