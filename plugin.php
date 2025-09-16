<?php
/*
Plugin Name:    Popular Clicks Extended - modified by dex
Plugin URI:     http://github.com/dexzhou/yourls-popular-clicks-extended
Description:    Paul Vaughan （http://github.com/vaughany/yourls-popular-clicks-extended） created this tool 8 years ago, but it didn't work well with time offsets. Now just modify line 18 and it will work, although it's not tied to the "definition('YOURLS_HOURS_OFFSET','+8')" in the user/config.php. I'm not a programmer and just modified it with an AI tool.
Version:        0.3
Release date:   2018-05-10
Author:         Paul Vaughan
Author URI:     http://github.com/vaughany/
*/
if ( !defined ('YOURLS_ABSPATH') ) { die(); }
define ( "PCE_DEBUG", false );
define ( "PCE_SEP", ' | ' );
define ( "PCE_REL_VER",  '0.3' );
define ( "PCE_REL_DATE", '2018-05-10' );
define ( "PCE_REPO", 'https://github.com/vaughany/yourls-popular-clicks-extended' );
// e.g. UTC+8 =（8*60*60=28800 s），input 8* 60 *60 below
define( "PCE_OFFSET", 8 * 60 * 60 );
define( "PCE_BLACKLIST", "" );
yourls_add_action( 'plugins_loaded', 'vaughany_pce_init' );
yourls_add_action( 'admin_page_after_table', 'vaughany_pce_recenthits' );

function vaughany_pce_init() {
    yourls_register_plugin_page( 'vaughany_pce', 'Popular Clicks Extended', 'vaughany_pce_display_page' );
}

function vaughany_pce_show_last_period( $period, $rows, $desc ) {
    global $ydb;
    if ( !is_int( $rows ) || $rows == 0 || $rows == null ) {
        $rows = 20;
    }
    $since = date( 'Y-m-d H:i:s', ( time() - $period + PCE_OFFSET ) );
$sql = "SELECT a.shorturl AS shorturl, COUNT(*) AS clicks, b.url AS longurl, b.title as title
        FROM " . YOURLS_DB_TABLE_LOG . " a, " . YOURLS_DB_TABLE_URL . " b
        WHERE a.shorturl = b.keyword
            AND DATE_ADD(a.click_time, INTERVAL :offset SECOND) >= :since  -- 新增时区偏移
        GROUP BY a.shorturl
        ORDER BY COUNT(*) DESC, shorturl ASC
        LIMIT :rows;";
    // 关键修改：添加 offset 绑定参数（值为 PCE_OFFSET）
    $binds = ['since' => $since, 'rows' => $rows, 'offset' => PCE_OFFSET];
    if ( PCE_DEBUG ) {
        echo '<p style="color: #f00;">(' . $sql . ')</p>';
    }
    if ( $results = $ydb->fetchObjects( $sql, $binds ) ) {
        $out = vaughany_pce_render_results( $results );
    } else {
        $out = '<p>No results for the chosen time period.</p>';
    }
    echo '<h3>Popular clicks for the last ' . $desc . ":</h3>";
    if (PCE_DEBUG) {
        echo '<p style="color: #f00;">(Period from ' . $since . ' to now.)</p>';
    }
    echo $out;
}

function vaughany_pce_show_specific_period( $period, $type, $rows, $desc ) {
    global $ydb;
    if ( !is_int($rows) || $rows == 0 || $rows == null ) {
        $rows = 20;
    }
    if ( $type == 'hour' ) {
        $from   = $period . ':00:00';
        $to     = $period . ':59:59';
    } else if ( $type == 'day' ) {
        $from   = $period . ' 00:00:00';
        $to     = $period . ' 23:59:59';
    } else if ( $type == 'week' ) {
        $from   = $period . ' 00:00:00';
        $to     = date( 'Y-m-d', strtotime( $period . ' + 6 days', time() + PCE_OFFSET ) ) . ' 23:59:59';
    } else if ( $type == 'month' ) {
        $from   = $period . '-01 00:00:00';
        $to     = date( 'Y-m-d', strtotime( $period . '-' . date( 't', strtotime( $from, time() + PCE_OFFSET ) ), time() + PCE_OFFSET ) ) . ' 23:59:59';
    } else if ( $type == 'year' ) {
        $from   = $period . '-01-01 00:00:00';
        $to     = $period . '-12-31 23:59:59';
    } else {
        $from   = '1970-01-01 00:00:00';
        $to     = date( 'Y-m-d H:i:s', 2147483647 );
    }
    // 关键修改：为 click_time 叠加 UTC+8 偏移（DATE_ADD 函数）
    $sql = "SELECT a.shorturl AS shorturl, COUNT(*) AS clicks, b.url AS longurl, b.title as title
        FROM " . YOURLS_DB_TABLE_LOG . " a, " . YOURLS_DB_TABLE_URL . " b
        WHERE a.shorturl = b.keyword
            AND DATE_ADD(a.click_time, INTERVAL :offset SECOND) >= :from  -- 新增时区偏移
            AND DATE_ADD(a.click_time, INTERVAL :offset SECOND) <= :to    -- 新增时区偏移
        GROUP BY a.shorturl
        ORDER BY COUNT(*) DESC, shorturl ASC
        LIMIT :rows;";
    // 关键修改：添加 offset 绑定参数（值为 PCE_OFFSET）
    $binds = ['from' => $from, 'to' => $to, 'rows' => $rows, 'offset' => PCE_OFFSET];
    if ( PCE_DEBUG ) {
        echo '<p style="color: #f00;">(' . $sql . ")</p>";
    }
    if ( $results = $ydb->fetchObjects( $sql, $binds ) ) {
        $out = vaughany_pce_render_results( $results );
    } else {
        $out = '<p>No results for the chosen time period.</p>';
    }
    echo '<h3>Popular clicks for ' . $desc . ":</h3>";
    if ( PCE_DEBUG ) {
        echo '<p style="color: #f00;">(Period from ' . $from . ' to ' . $to . ".)</p>";
    }
    echo $out;
}

function vaughany_pce_render_results( $results ) {
    $total = 0;
    $out = '<table>';
    $out .= '<tr><th>Hits</th><th>Short URL</th><th>Website</th></tr>';
    foreach ( $results as $result ) {
        $total += $result->clicks;
        $out .= '<tr>';
        $out .= '<td>' . $result->clicks . '</td>';
        $out .= '<td><a href="' . YOURLS_SITE . '/' . $result->shorturl . '+" target="blank">' . $result->shorturl . '</a></td>';
        $out .= '<td><a href="' . $result->longurl . '" target="blank">' . $result->title . '</a></td>';
    }
    $out .= '</table>';
    return $out;
}

function vaughany_show_log( $rows = 20) {
    global $ydb;
    if ( !is_int($rows) || $rows == 0 || $rows == null || $rows == '' ) {
        $rows = 20;
    }
    // 关键修改：查询时将 click_time 转换为 UTC+8 时间（DATE_ADD 函数）
    $sql = "SELECT DATE_ADD(a.click_time, INTERVAL :offset SECOND) AS click_time,  -- 新增时区偏移
                   a.ip_address, 
                   a.country_code, 
                   a.referrer, 
                   a.shorturl AS shorturl, 
                   b.url AS longurl, 
                   b.title as title
        FROM " . YOURLS_DB_TABLE_LOG . " a, " . YOURLS_DB_TABLE_URL . " b
        WHERE a.shorturl = b.keyword
        ORDER BY a.click_time DESC  -- 按原时间排序（不影响）
        LIMIT :rows;";
    // 关键修改：添加 offset 绑定参数
    $binds = ['rows' => $rows, 'offset' => PCE_OFFSET];
    if ( PCE_DEBUG ) {
        echo '<p style="color: #f00;">(' . $sql . ")</p>" . PHP_EOL;
    }
    if ( $results = $ydb->fetchObjects( $sql, $binds ) ) {
        $out = '<ol>';
        foreach ( $results as $result ) {
            $out .= '<li>';
            $out .= $result->click_time . PCE_SEP;  // 此时显示的已是 UTC+8 时间
            $out .= $result->country_code . PCE_SEP;
            $out .= $result->ip_address . PCE_SEP;
            $out .= $result->referrer . PCE_SEP;
            $out .= 'click  <a href="' . YOURLS_SITE . '/' . $result->shorturl . '+" target="blank">' . $result->shorturl . '</a> / ';
            $out .= 'to <a href="' . $result->longurl . '" target="blank">' . $result->title . '</a>' . PCE_SEP;
            $out .= '</li>';
        }
        $out .= "</ol>" . PHP_EOL;
    } else {
        $out = '<p>No logs to display.</p>' . PHP_EOL;
    }
    echo $out;
}

function vaughany_pce_recenthits() {
    echo vaughany_show_log();
}

function vaughany_pce_display_page() {
    yourls_e( '<h2>Popular Clicks Extended</h2>' );
    yourls_e( '<p>This report shows the most popular clicks for the selected time periods as of ' . date( 'jS F Y, g:ia', time() + PCE_OFFSET ) . '.</p>' );
    yourls_e( '<p>Legend: <em>Position. Clicks' . PCE_SEP . 'Short URL' . PCE_SEP . 'Page</em></p>' );
    echo "<hr>" ;
?>
<div id="tabs">
    <div class="wrap_unfloat">
        <ul id="headers" class="toggle_display stat_tab">
            <li class="selected"><a href="#stat_tab_stats"><h2>'Period'</h2></a></li>
            <li><a href="#stat_tab_location"><h2>Last 'Period'</h2></a></li>
            <li><a href="#stat_tab_sources"><h2>Something Else</h2></a></li>
            <li><a href="#stat_tab_share"><h2>Settings</h2></a></li>
        </ul>
    </div>
    <div id="stat_tab_stats" class="tab">
        <p>Content coming soon.</p>
	<?php echo vaughany_pce_this_period() ?>
    </div>
    <div id="stat_tab_location" class="tab">
        <p>Content coming soon.</p>
	<?php echo vaughany_pce_last_period() ?>
    </div>
    <div id="stat_tab_sources" class="tab">
        <p>Content coming soon.</p>
	<?php echo vaughany_pce_something() ?>
    </div>
    <div id="stat_tab_share" class="tab">
	<p>Probably a form or something.</p>
    </div>
</div>
<?php
    echo "<hr>" ;
    yourls_e( '<h2>Popular clicks for &quot;<em>period</em>&quot;</h2>' );
    vaughany_pce_show_specific_period( date( 'Y-m-d H', time() + PCE_OFFSET ), 'hour', null, 'this hour (' . date( 'jS F Y, ga', time() + PCE_OFFSET ) . ' to ' . date( 'ga', strtotime( '+ 1 hour', time() + PCE_OFFSET ) ) . ') (so far)' );
    vaughany_pce_show_specific_period( date( 'Y-m-d H', strtotime( '- 1 hour', time() + PCE_OFFSET ) ), 'hour', null, 'the previous hour (' . date( 'jS F Y, ga', strtotime( '- 1 hour', time() + PCE_OFFSET ) ) . ' to ' . date( 'ga', time() + PCE_OFFSET ) . ')' );
    vaughany_pce_show_specific_period( date( 'Y-m-d', time() + PCE_OFFSET ), 'day', null, 'today (' . date( 'jS F Y', time() + PCE_OFFSET ) . ') (so far)' );
    vaughany_pce_show_specific_period( date( 'Y-m-d', strtotime( '- 1 day', time() + PCE_OFFSET ) ), 'day', null, 'yesterday (' . date( 'jS F Y', strtotime( '- 1 day', time() + PCE_OFFSET ) ) . ')' );
    vaughany_pce_show_specific_period( date( 'Y-m-d', strtotime( 'monday this week', time() + PCE_OFFSET ) ), 'week', null, 'this week (beginning ' . date( 'jS F Y', strtotime( 'monday this week', time() + PCE_OFFSET ) ) . ') (so far)' );
    vaughany_pce_show_specific_period( date( 'Y-m-d', strtotime( 'monday this week - 7 days', time() + PCE_OFFSET ) ), 'week', null, 'last week  (beginning ' . date( 'jS F Y', strtotime( 'monday this week - 7 days', time() + PCE_OFFSET ) ) . ')' );
    vaughany_pce_show_specific_period( date( 'Y-m', time() + PCE_OFFSET ), 'month', null, 'this month (' . date( 'F Y', time() + PCE_OFFSET ) . ') (so far)' );
    vaughany_pce_show_specific_period( date( 'Y-m', strtotime( '- 1 month', time() + PCE_OFFSET ) ), 'month', null, 'last month (' . date( 'F Y', strtotime( '- 1 month', time() + PCE_OFFSET ) ) . ')' );
    vaughany_pce_show_specific_period( date( 'Y', time() + PCE_OFFSET ), 'year', null, 'this year (' . date( 'Y', time() + PCE_OFFSET ) . ') (so far)');
    vaughany_pce_show_specific_period( date( 'Y', strtotime( '- 1 year', time() + PCE_OFFSET ) ), 'year', null, 'last year (' . date('Y', strtotime( '- 1 year', time() + PCE_OFFSET ) ) . ')' );
    echo "<hr>";
    yourls_e( '<h2>Popular clicks for the last &quot;<em>period</em>&quot;</h2>' );
    vaughany_pce_show_last_period( 60 * 5,                  null, '5 minutes');
    vaughany_pce_show_last_period( 60 * 30,                 null, '30 minutes');
    vaughany_pce_show_last_period( 60 * 60,                 null, 'hour');
    vaughany_pce_show_last_period( 60 * 60 * 6,             null, '6 hours');
    vaughany_pce_show_last_period( 60 * 60 * 12,            null, '12 hours');
    vaughany_pce_show_last_period( 60 * 60 * 24,            null, '24 hours');
    vaughany_pce_show_last_period( 60 * 60 * 24 * 2,        null, '2 days');
    vaughany_pce_show_last_period( 60 * 60 * 24 * 7,        null, 'week');
    vaughany_pce_show_last_period( 60 * 60 * 24 * 14,       null, '2 weeks');
    vaughany_pce_show_last_period( 60 * 60 * 24 * 30,       null, 'month');
    vaughany_pce_show_last_period( 60 * 60 * 24 * 60,       null, '2 months');
    vaughany_pce_show_last_period( 60 * 60 * 24 * 90,       null, '3 months');
    vaughany_pce_show_last_period( 60 * 60 * 24 * 180,      null, '6 months');
    vaughany_pce_show_last_period( 60 * 60 * 24 * 365,      null, 'year');
    vaughany_pce_show_last_period( 60 * 60 * 24 * 365 * 2,  null, '2 years');
    vaughany_pce_show_last_period( 60 * 60 * 24 * 365 * 3,  null, '3 years');
    vaughany_pce_show_last_period( 60 * 60 * 24 * 365 * 4,  null, '4 years');
    vaughany_pce_show_last_period( time(),                  null, 'billion years');
    echo "<hr>";
    yourls_e( '<h2>Recently used short links</h2>' );
    vaughany_show_log();
    echo "<hr>";
    if ( PCE_DEBUG ) {
        echo '<p style="color: #f00;">';
        echo 'Last monday: ' . date( 'Y-m-d', strtotime( 'last monday', time() + PCE_OFFSET ) ) . "<br>" . PHP_EOL;
        echo 'Monday before: ' . date( 'Y-m-d', strtotime( 'last monday - 7 days', time() + PCE_OFFSET ) ) . "<br>" . PHP_EOL;
        echo 'Last month: ' . date( 'Y-m', strtotime( '- 1 month', time() + PCE_OFFSET ) ) . "<br>" . PHP_EOL;
        echo '32-bit max Unix int: ' . date( 'Y-m-d H:i:s', 2147483647) . PHP_EOL;
        echo '</p>';
    }
    echo '<p>This plugin by <a href="https://github.com/vaughany/">Paul Vaughan</a>, version ' . PCE_REL_VER . ' (' . PCE_REL_DATE .
        '), heavily inspired by <a href="https://github.com/miconda/yourls">Popular Clicks</a>, is <a href="' . PCE_REPO .
        '">available on GitHub</a> (<a href="' . PCE_REPO . '/issues">file a bug here</a>).</p>' . PHP_EOL;
}
