<?php

$fixtures = get_fixtures();

// All teams must get 4 games.
check__game_count($fixtures, 4);
check__possible_matches($fixtures);
check__consecutive_games($fixtures, 1);
check__a_vs_b($fixtures);
check__duplicates($fixtures);
// TODO Check teams games are all in the same group?

/**
 * Read the fixtures from the CSV file.
 * Format: each row is a round.
 *         each group of 4 columns is a pitch. The 4 columns are:
 *         Grouping, Team 1, (vs) Team 2, [blank].
 *
 * @return array
 */
function get_fixtures() {
  $handle = fopen(__DIR__ . '/fixtures.csv', 'r');

  $fixtures = array();

  $round = 1;
  while ($row = fgetcsv($handle, NULL, "\t")) {
    $games = array_chunk($row, 4);

    foreach ($games as $i => $game) {
      $pitch_no = $i + 1;

      if (!array_filter($game)) {
        error("Pitch $pitch_no is empty in round $round");
        continue;
      }

      $fixtures[$round][$pitch_no] = array(
        'grouping' => $game[0],
        't1' => $game[1],
        't2' => $game[2],
      );
    }
    $round++;
  }

  return $fixtures;
}

/**
 * Check that each team get's a specified number of games.
 * @todo warn on byes.
 */
function check__game_count($fixtures, $required_games) {

  $checker = array();

  foreach ($fixtures as $round_no => $round) {
    foreach ($round as $pitch_no => $game) {
      if (empty($checker[$game['grouping']][$game['t1']])) {
        $checker[$game['grouping']][$game['t1']] = 0;
      }

      $checker[$game['grouping']][$game['t1']]++;

      if (empty($checker[$game['grouping']][$game['t2']])) {
        $checker[$game['grouping']][$game['t2']] = 0;
      }

      $checker[$game['grouping']][$game['t2']]++;
    }
  }

  foreach ($checker as $group => $teams) {
    foreach ($teams as $team_name => $game_count) {
      if ($game_count != $required_games) {
        error("$group - " . $team_name . ' has ' . $game_count . ' games');
      }
    }
  }


}

/**
 * Check that no team plays itself.
 */
function check__possible_matches($fixtures) {
  foreach ($fixtures as $round_no => $round) {
    foreach ($round as $pitch_no => $game) {
      if ($game['t1'] == $game['t2']) {
        error($game['t1'] . ' plays themselves in round ' . $round_no . ' on pitch ' . $pitch_no);
      }
    }
  }
}

/**
 * Check that every team has a break of at least n matches between each game.
 *
 * min_gap == 0 will check that no team is playing on two pitches at the same time
 * min_gap == 1 will check that every team has at least one games break between each match.
 * min_gap == 2 will check that every team has at least two games break between each match etc.
 *
 */
function check__consecutive_games($fixtures, $min_gap = 0) {

  // Look ahead one row. No team should play twice in a row.
  // For each team, we could calculate the spread of their games.
  //
  // We gather the group/team name. And the gap between each of their games.
  // No team can play two games at the same time.

  foreach ($fixtures as $round_no => $round) {
    foreach ($round as $pitch_no => $game) {
      foreach (_get_teams($game) as $t => $team) {

        // _find_team_in_round will find the current game being checked if we
        // don't exclude it.
        $ignore = array(
          'grouping' => $game['grouping'],
          'pitch' => $pitch_no,
        );

        for ($i = 0; $i <= $min_gap; $i++) {
          $test_round_no = $round_no + $i;
          if (isset($fixtures[$test_round_no])) {

            $test_round = $fixtures[$test_round_no];
            $dupe = _find_team_in_round($test_round, $game['grouping'], $team);

            if ($dupe && ($test_round_no != $round_no || $dupe != $ignore)) {
              error("{$game['grouping']} - $team is playing in round $round_no on pitch $pitch_no and round $test_round_no on pitch {$dupe['pitch']}");
            }
          }
        }
      }
    }
  }

}

function check__a_vs_b($fixtures) {
  foreach ($fixtures as $round_no => $round) {
    foreach ($round as $pitch_no => $game) {
      if ($game['t1'] !== $game['t2'] && _get_team_base_name($game['t1']) == _get_team_base_name($game['t2'])) {
        error("{$game['grouping']} - {$game['t1']} plays {$game['t2']} in round $round_no");
      }
    }
  }
}

function check__duplicates($fixtures) {
  foreach ($fixtures as $round_no1 => $round1) {
    foreach ($round1 as $pitch_no1 => $game1) {
      foreach ($fixtures as $round_no2 => $round2) {
        foreach ($round2 as $pitch_no2 => $game2) {

          // Don't check the same fixture against itself.
          if ($round_no1 === $round_no2 && $pitch_no1 === $pitch_no2) {
            continue;
          }

          // Don't repeat the same checks.
          if ($round_no1 >= $round_no2) {
            continue;
          }

          // Don't check matches across groups.
          if ($game1['grouping'] !== $game2['grouping']) {
            continue;
          }

          $match =
            ($game1['t1'] == $game2['t1'] || $game1['t1'] == $game2['t2'])
            && ($game1['t2'] == $game2['t1'] || $game1['t2'] == $game2['t2']);

          if ($match) {
            error("{$game1['grouping']} - {$game1['t1']} plays {$game1['t2']} in round $round_no1 and again in $round_no2");
          }
        }
      }
    }
  }
}

function _find_team_in_round($round, $grouping, $search_team) {

  foreach ($round as $pitch_no => $game) {
    foreach (_get_teams($game) as $t => $team) {
      if ($search_team == $team && $game['grouping'] == $grouping) {
        return array(
          'grouping' => $grouping,
          'pitch' => $pitch_no,
        );
      }
    }
  }
}

function _get_teams($game) {
  return array('t1' => $game['t1'], 't2' => $game['t2']);
}

function _get_team_base_name($name) {
  $name = trim($name);
  // Match the entire team name, if it ends up with a space and a capital
  // letter.
  if (preg_match('/^(.*)\s+([A-Za-z])$/', $name, $matches)) {
    return $matches[1];
  }
  return $name;
}

function error($message) {
  echo $message . "\n";
}

