$.fn.datepicker.defaults.language = 'cn';
$.fn.datepicker.defaults.format = 'yyyy-mm-dd';
$.fn.datepicker.dates.cn = {
    days: ["日", "一", "二", "三", "四", "五", "六", "日"],
    daysShort: ["日", "一", "二", "三", "四", "五", "六", "日"],
    daysMin: ["日", "一", "二", "三", "四", "五", "六", "日"],
    months: ["一月", "二月", "三月", "四月", "五月", "六月", "七月", "八月", "九月", "十月", "十一月", "十二月"],
    monthsShort: ["一月", "二月", "三月", "四月", "五月", "六月", "七月", "八月", "九月", "十月", "十一月", "十二月"],
    today: "今天",
    clear: "清除"
};

$(function(){
    $('select').select2();
    $(document.body).delegate('a.download-csv', 'click', function(event){
        event.preventDefault();
        const table_id = $(event.srcElement).attr('data-table-id');
        download_table_as_csv(table_id);
    });
});

function get_dtoption(name, option)
{
    function fnClick ( nButton, oConfig, flash ) {
        var text = this.fnGetTableData(oConfig);
        var ext_rows = [];
        $('#' + this.s.dt.sTableId).find('tbody.summary tr').each(function(){
            var ext_row = [];
            $(this).find('td').each(function(){
                ext_row.push(oConfig.sFieldBoundary + $.trim($(this).text()).replace(/"/g, '\\"') + oConfig.sFieldBoundary);
            });
            ext_rows.push(ext_row.join(oConfig.sFieldSeperator));
        });

        if (ext_rows.length) {
            rows = text.split("\n");
            Array.prototype.splice.apply(rows, [1, 0].concat(ext_rows));
            text = rows.join("\n");
        }

        var filename = document.title.match(/^(\S+)/)[0];

        var times = [];

        $('input.date-picker').each(function(){
            times.push($(this).val().replace(/-/g, ''));
        });

        if (times.length) {
            filename += '_' + times.join('~');
        }

        flash.setFileName(filename + '.csv');
        this.fnSetText( flash, text );
    }

    var def = {
        // Internationalisation. For more info refer to http://datatables.net/manual/i18n
        "language": {
            "aria": {
                "sortAscending": ": activate to sort column ascending",
                "sortDescending": ": activate to sort column descending"
            },
            "emptyTable": "没有数据",
            "info": "当前显示 _START_ 到 _END_， 共 _TOTAL_ 条记录",
            "infoEmpty": "未找到记录",
            "infoFiltered": "(filtered1 from _MAX_ total entries)",
            "lengthMenu": "显示 _MENU_ 条",
            "search": "搜索:",
            "zeroRecords": "No matching records found"
        },

        bPaginate : false,

        /*
        "lengthMenu": [
            [20, 50, 100, -1],
            [20, 50, 100, "All"] // change per page values here
        ],

        // set the initial value
        "pageLength": -1,
        */

        "dom": "<'row' <'col-md-12'T>><'row'<'col-md-6 col-sm-12'l><'col-md-6 col-sm-12'f>r><'table-scrollable't><'row'<'col-md-5 col-sm-12'i><'col-md-7 col-sm-12'p>>", // horizobtal scrollable datatable

        // Uncomment below line("dom" parameter) to fix the dropdown overflow issue in the datatable cells. The default datatable layout
        // setup uses scrollable div(table-scrollable) with overflow:auto to enable vertical scroll(see: assets/global/plugins/datatables/plugins/bootstrap/dataTables.bootstrap.js).
        // So when dropdowns used the scrollable div should be removed.
        //"dom": "<'row' <'col-md-12'T>><'row'<'col-md-6 col-sm-12'l><'col-md-6 col-sm-12'f>r>t<'row'<'col-md-5 col-sm-12'i><'col-md-7 col-sm-12'p>>",

        "tableTools": {
            "sSwfPath": "../../../assets/global/plugins/datatables/extensions/TableTools/swf/copy_csv_xls_pdf.swf",
            "aButtons": [{
                "sExtends": "pdf",
                "sButtonText": "PDF"
            }, {
                "sExtends": "csv",
                "sButtonText": "CSV",
                "fnClick" : fnClick
            }, {
                "sExtends": "xls",
                "sButtonText": "Excel",
                "fnClick" : fnClick
            }, {
                "sExtends": "print",
                "sButtonText": "打印",
                "sInfo": '按下 "CTR+P" 打印，按下 "ESC" 退出打印模式',
                "sMessage": "BY DDY.ADEAZ.COM"
            }]
        },

        //hasFixedHeader : true,

        //fixedHeaderOptions : {
        //    offsetTop : $('.navbar').height()
        //},

        fnDrawCallback : function($dt){
            var $table = $('#' + $dt.sTableId);
            $table.closest('div.dataTables_wrapper').find('select').select2();
            $table.find('tbody.items').before($table.find('tbody.summary').remove());
        }
    };

    return $.extend(true, def, option);
}


function set_table_options (tid, options) {
    var $table = $('#rtable-' + tid);

    $table.data('options', options);

    if ($table.find('tr.child')) {
        var $trs = $table.find('tr').get();
        for (var i = 0; i < $trs.length; i++) {
            if (!$($trs[i]).is('.child') && $($trs[i+1]).is('.child')) {
                $($trs[i]).find('td:first').prepend('<a href="javascript:;" onclick="toggle_child_row(this)"><i class="fa fa-plus"></i></a>&nbsp;');
            }
        }
        $table.find('tr.child').hide();
    }

    if (options.dt === false || options.merge_cell) {
        $table.closest('.portlet').find('.portlet-title .actions').prepend('<a class="btn btn-xs green download-csv" data-table-id="rtable-' + tid + '" href="javascript:;">下载CSV</a>');
        return;
    }

    var $avg = $table.find('tbody.avg tr.avg'),
        $sum = $table.find('tbody.sum tr.sum'),
        col_map = {};

    $table.find('thead th').each(function(i, th){
        col_map[$.trim($(th).text())] = i;
    });

    if ($avg.length || $sum.length) {
        var first = true;

        $table.on('draw.dt', function(event, $dt){

            if (first) {
                first = false;
                return;
            }

            var sum = {}, avg = {};
            var $items = $table.find('tbody.items tr[role=row]:not(.summary)');

            $.each(options.fields, function(name, field_config) {
                if (field_config.count) {
                    var col_index = col_map[name] || name;

                    if (!/^\d+$/.test(col_index)) {
                        //console.error('TABLE[' + tid + ']找不到列[' + name + ']');
                        return;
                    }

                    sum[col_index] = 0;

                    $items.each(function(){
                        sum[col_index] += 1 * (parseInt($.trim($(this).find('td:eq(' + col_index + ')').text()).replace(/,/g, '')) || 0);
                    });
                }
            });

            $.each(sum, function(index, value) {
                var force_to_int = !is_float(value);

                if ($sum.length) {
                    $sum.find('td:eq(' + index + ')').html(fnum(value, force_to_int));
                }

                if ($avg.length) {
                    avg[index] = $items.length == 0 ? 0 : value / $items.length;
                    $avg.find('td:eq(' + index + ')').html(fnum(avg[index], force_to_int));
                }
            });

            $.each([[$sum, sum], [$avg, avg]], function(i, t) {
                if (!t[0].length) return;
                $.each(options.fields, function(name, field_config) {
                    if (field_config.def) {
                        var exp = field_config.def.replace(/\{(.+?)\}/g, function(all, name){
                            var col_index = col_map[name] || name;

                            if (!/^\d+$/.test(col_index)) {
                                //console.error('TABLE[' + tid + ']找不到列[' + name + ']');
                                return;
                            }

                            return t[1][col_index] || 0;
                        });

                        //console.debug('execute exp:' + exp);

                        var temp = '-';
                        try {
                            eval('temp=' + exp);
                        } catch (e) {
                            //console.error('执行表达式错误：' + exp);
                        }

                        if (temp != '-') {
                            temp = fnum(temp, !is_float(temp));
                        }

                        var col_index = col_map[name] || name;

                        if (!/^\d+$/.test(col_index)) {
                            //console.error('TABLE[' + tid + ']找不到列[' + name + ']');
                            return;
                        }

                        t[0].find('td:eq(' + col_index + ')').html(temp);
                    }
                });
            });
        });
    }
}

function dt_options (tid) {

    var $table = $('#rtable-' + tid);

    if ($table.data('dt_options')) {

        return $table.data('dt_options');
    }

    var table_options = $table.data('options') || {};
    var options = table_options.dt ? table_options.dt : {};
    var vw = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
    if ($table.find('tbody.items tr').length > 20 && vw > 1000) {
        options.hasFixedHeader = true;
        options.fixedHeaderOptions = {
            offsetTop : $('.navbar').height()
        };
    }

    var is_time = /时间|日期|time/.test($table.find('thead th:eq(0)').text());
    if (is_time && !options.order) {
        var $trs = $table.find('tbody.items tr');
        if ($trs.length > 1 &&
            $trs.eq(0).find('td:eq(0)').text() != $trs.eq(1).find('td:eq(0)').text()
        ) {
            options.order = [
                [0, 'desc']
            ];
        }
    }

    if (!options.order) { options.order = []; }

    options.retrieve = true;

    $table.data('dt_options', get_dtoption('report', options));

    return $table.data('dt_options');
}

function render_dtable(tid)
{
    var $table = $('#rtable-' + tid);
    $table.DataTable(dt_options(tid));
}

var parseNumberFromElem = function ($elem) {
    return 1 * (parseFloat($.trim($elem.text()).replace(/\s+.+$/, '').replace(/[,%]/g, '')) || 0);
};

var render_chart = (function(){

    function _table(report_id) {
        if (typeof report_id == 'object') return report_id;

        return $('#rtable-' + report_id);
    }

    function get_table_th(report_id)
    {
        var $ths = _table(report_id).find('thead tr:first th');
        return $ths.map(e => {
            return $.trim($ths.eq(e).text())
        });
    }

    function get_column_index(report_id, name, default_index)
    {
        if (/^\d+$/.test(name)) return name;

        var $ths = _table(report_id).find('thead tr:first th');

        for (var i = 0; i < $ths.length; i++) {
            if ($.trim($ths.eq(i).text()) == name) {
                return i;
            }
        }

        return typeof(default_index) == 'undefined' ? -1 : default_index;
    }

    var chart_tpl = {};
    chart_tpl['serial'] = {
        "type": "serial",
        "theme": "light",
        "categoryField": "time",
        "categoryAxis": {
            "parseDates": true,
            "minorGridEnabled": true
        },
        "chartCursor": {
            "pan": true,
            "valueLineEnabled": true,
            "valueLineBalloonEnabled": true,
            "cursorAlpha": 0,
            "valueLineAlpha": 0.2
        },
        "legend": {
            "useGraphSettings": true,
            "position": "top"
        },
        "balloon": {
            "borderThickness": 1,
            "shadowAlpha": 0
        },
        "export": {
            "enabled": true,
            "libs": {
                "path": "/assets/global/plugins/amcharts/amcharts/plugins/export/libs/"
            }
        },
        "graphs" : [],
        "dataProvider" : []
    };

    chart_tpl['pie']= {
        "type" : "pie",
        "startDuration": 0,
        "theme": "light",
        "addClassNames": true,
        "legend":{
            "position":"right",
            "marginRight":100,
            "autoMargins":false
        },
        "innerRadius": "30%",
        "defs": {
            "filter": [{
                "id": "shadow",
                "width": "200%",
                "height": "200%",
                "feOffset": {
                    "result": "offOut",
                    "in": "SourceAlpha",
                    "dx": 0,
                    "dy": 0
                },
                "feGaussianBlur": {
                    "result": "blurOut",
                    "in": "offOut",
                    "stdDeviation": 5
                },
                "feBlend": {
                    "in": "SourceGraphic",
                    "in2": "blurOut",
                    "mode": "normal"
                }
            }]
        },
        "export": {
            "enabled": true,
            "libs": {
                "path": "/assets/global/plugins/amcharts/amcharts/plugins/export/libs/"
            }
        },
        "titleField": "data-0",
        "valueField": "data-1",
        "dataProvider" : []
    };

    function render_amchart(report_id, index, options)
    {
        var $table = _table(report_id);

        options = $.extend(true, {}, chart_tpl[options.type || 'serial'] || chart_tpl['serial'], options);

        var graph_value_fields = [];
        var invalid_graph_index = [];

        $.each(options.graphs, function(i, graph) {
            graph = $.extend(true, {
                "bullet": "round",
                "bulletBorderAlpha": 1,
                "bulletColor": "#FFFFFF",
                "bulletSize": 5,
                "hideBulletsCount": 50,
                "lineThickness": 2,
                //"lineColor": "#e1ede9",
                "type": "smoothedLine",
                //"dashLength": 5,
                "title": graph.valueField,
                "useLineColorForBulletBorder": true,
                "balloonText": "[[title]]<br/><b style='font-size: 130%'>[[value]]</b>"
            }, graph);

            var value_field = graph.valueField;
            var value_column_index = get_column_index($table, value_field);
            if (value_column_index == -1) {
                console.error("CHART[" + report_id + "] 列[" + value_field + "]找不到");
                invalid_graph_index.push(i);
                return;
            }

            graph.valueField = 'data-' + value_column_index;

            graph_value_fields.push([graph.valueField, value_column_index]);

            options.graphs[i] = graph;
        });

        $.each(invalid_graph_index, function(i, j) {
            options.graphs.splice(j - i, 1);
        });

        var $rows = $table.find('tbody.items tr');

        if (options.type == 'serial' && typeof options.dataDateFormat == 'undefined') {
            var time_format = $rows.eq(0).find('td:first').text()
                .replace(/\d{4}-\d{2}-\d{2}/, 'YYYY-MM-DD')
                .replace(/\d{2}:\d{2}:\d{2}/, 'JJ:NN:SS')
                .replace(/\d{2}:\d{2}/, 'JJ:NN')
                .replace(/^\d{10}$/, 'JJ');

            console.debug('CHART[' + report_id + '] time_format:' + $rows.eq(0).find('td:first').text() + '=>' + time_format);

            if (/^[YMDJNS: -]+$/.test(time_format)) {
                if (/NN/.test(time_format)) {
                    options.categoryAxis.minPeriod = 'mm';
                } else if (/JJ/.test(time_format)) {
                    options.categoryAxis.minPeriod = 'hh';
                } else {
                    options.categoryAxis.minPeriod = 'DD';
                }

                //if (time_format == 'MM-DD JJ:NN') time_format = 'JJ:NN';
                options.dataDateFormat = time_format;
                options.chartCursor.categoryBalloonDateFormat = time_format;
                console.debug('CHART[' + report_id + '] set dataDateFormat auto:' + time_format);
                //time_format = time_format.replace(/(YYYY-|:SS)/g, '');
                //if (time_format == 'MM-DD JJ:NN') time_format = 'JJ:NN';
                $.map(options.graphs, function(graph) {
                    graph.dateFormat = time_format;
                });
            } else {
                options.categoryField = "_x";
                options.categoryAxis = {
                    "gridPosition": "start"
                };
            }
        }
        // 防止 x轴 文本相互覆盖
        if (options.type == 'serial' && $rows.length > 20) {
            options.categoryAxis = {
                ...options.categoryAxis,
                labelRotation: 35
            }
        }

        if (options.type == 'pie') {
            var _dataProvider = {},
                _keyIndex = graph_value_fields[0],
                _valIndex = graph_value_fields[1],
                _dataProviderFields = [
                    _keyIndex[0],
                    _valIndex[0]
                ];
            options.valueField = graph_value_fields[1][0];
            options.titleField = graph_value_fields[0][0];
        }

        $rows.each(function() {
            var $cells = $(this).find('td');
            var data_item = {}, c;
            if (options.type == 'serial') {
                data_item[options.categoryField] = $.trim($cells.eq(0).text());
            }

            if (options.type == 'pie') {//如果是饼图的时候，对数据做

                var _key = $.trim($cells.eq(_keyIndex[1]).text());
              //var _val = $.trim($cells.eq(_valIndex[1]).text());
              // var _val = 1 * (_val.replace(/[,%]/g, '') || 0) || 0;
                var _val = parseNumberFromElem($cells.eq(_valIndex[1]));

                if (typeof _dataProvider[_key] == 'undefined') {
                    _dataProvider[_key] = 0;
                }
                _dataProvider[_key] += _val;

            } else {
                for (var i = 0; i < graph_value_fields.length; i++) {
                    c = graph_value_fields[i];
                  //var s = $.trim($cells.eq(c[1]).text());
                  //var n = 1 * (s.replace(/[,%]/g, '') || 0);
                  //data_item[c[0]] = isNaN(n) ? s : n;
                    data_item[c[0]] = parseNumberFromElem($cells.eq(c[1]));
                }

                options.dataProvider.push(data_item);
            }
        });

        //检测数据是否是按时间升序存储的，否则图表显示会存在问题
        options.dataProvider.sort(function(a, b) {
            if (a[options.categoryField] < b[options.categoryField]) {
                return -1;
            }
            if (a[options.categoryField] > b[options.categoryField]) {
                return 1;
            }
            return 0;
        });

        if (options.type == 'pie') {
            if (_dataProvider) {
                var isFloat = false;
                if (typeof options.is_float != 'undefined') {
                    isFloat = !!options.is_float;
                }
                for(var i in _dataProvider) {
                    var data_item = {};
                    data_item[_dataProviderFields[0]] = i;
                    if (isFloat) {
                        _dataProvider[i] = _dataProvider[i] . toFixed(options.is_float)*1;
                    }
                    data_item[_dataProviderFields[1]] = _dataProvider[i];
                    options.dataProvider.push(data_item);
                }
            }
        }

        var chart_cnt_id = 'rchart-' + report_id + '-' + index;

        $('#rchart-' + report_id).append('<div class="chart" id="' + chart_cnt_id +  '"></div>');

        options['allLabels'] = [{
            "text": report_id,
            "bold": true,
            "x": "95%",
            "y": "10%",
            "rotation": 0,
            "width": "100%",
            "align": "middle"
        }]

        // options['titles'] = [
        //     {
        //         "text": report_id
        //     }
        // ]

        options['export']['fileName'] = report_id;

        console.debug('chart option:', options);

        AmCharts.makeChart(chart_cnt_id, options);

        $('#' + chart_cnt_id).find('a[href*="amcharts"]').css('opacity', .1);
    }

    return function (report_id, options_arr) {
        console.log("render chart " + report_id, options_arr);
        console.debug("render chart " + report_id, options);

        if (!(typeof options_arr == 'object' && typeof options_arr.length !== 'undefined')) {
            options_arr = [ options_arr ];
        }

        for (var i = 0; i < options_arr.length; i++) {
            var options = options_arr[i];

            var columns = '';

            if (typeof options == 'string') {
                columns = options;
                options = {};
            }

            if (columns == "__auto__") {
                columns = get_table_th(report_id).filter(i => i > 0).toArray().join(',');
            }

            if (typeof options != 'object') {
                console.error("CHART[" + report_id + "]配置不是个对象");
                return;
            }

            if (!options.graphs) {
                options.graphs = [];
            }

            if (options.fields) { columns = options.fields; }

            if (columns) {
                $.each(columns.split(/,/), function(i, value_field) {
                    options.graphs.push({
                        valueField : value_field
                    });
                });
            }

            options_arr[i] = options;
        }

        $.each(options_arr, function(i, amchart_option) {
            console.log('render chart ' + report_id, amchart_option);
            render_amchart(report_id, i, amchart_option);
        });
    };
})();

function round(num, n)
{
    n = n || 0;
    return Math.round(num * Math.pow(10, n)) / Math.pow(10, n);
}

function is_float(num)
{
    return /\./.test('' + num);
}

function fnum(num, round)
{
    if (round) {
        num = Math.round(num) + '';
    } else {
        num = (Math.round(num * 100) / 100) + '';
    }

    return num.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}


