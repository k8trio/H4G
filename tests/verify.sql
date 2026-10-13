-- Integrity checks for H4G team mode. Every query should return ZERO rows.

-- 1. teams.points must equal SUM(solves.points_awarded) for every team.
SELECT t.id, t.name, t.points, COALESCE(SUM(s.points_awarded), 0) AS sum_awarded
FROM teams t LEFT JOIN solves s ON s.team_id = t.id
GROUP BY t.id
HAVING t.points <> COALESCE(SUM(s.points_awarded), 0);

-- 2. No team has more than 4 members.
SELECT team_id, COUNT(*) AS members FROM players
WHERE team_id IS NOT NULL GROUP BY team_id HAVING COUNT(*) > 4;

-- 3. Solve ranks are unique per challenge.
SELECT problem, solve_rank, COUNT(*) FROM solves
GROUP BY problem, solve_rank HAVING COUNT(*) > 1;

-- 4. At most one solve carries the First Blood flag, and it matches event_first_blood.
SELECT COUNT(*) AS first_blood_solves FROM solves WHERE first_blood
HAVING COUNT(*) > 1
    OR COUNT(*) <> (SELECT COUNT(*) FROM event_first_blood);

SELECT s.team_id, s.problem FROM solves s
WHERE s.first_blood AND NOT EXISTS (
  SELECT 1 FROM event_first_blood f WHERE f.team_id = s.team_id AND f.problem = s.problem);

-- 5. Solo teams have exactly one member.
SELECT t.id, t.name, COUNT(p.id) AS members FROM teams t LEFT JOIN players p ON p.team_id = t.id
WHERE t.is_solo GROUP BY t.id HAVING COUNT(p.id) <> 1;
