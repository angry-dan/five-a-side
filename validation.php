<?php

$fixtures = get_fixtures();

// All teams must get 4 games.
check__game_count($fixtures, 4);
check__possible_matches($fixtures);
check__consecutive_games($fixtures, 1);

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
        error('group ' . $group . ', "' . $team_name . '" only has ' . $game_count . ' games.');
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

function error($message) {
  echo $message . "\n";
}

