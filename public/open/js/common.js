function download_table_as_csv(table_id, separator = ',') {
    // Select rows from table_id
    var rows = document.querySelectorAll('table#' + table_id + ' tr');
    // Construct csv
    var csv = [];
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('td, th');
        for (var j = 0; j < cols.length; j++) {
            // Clean innertext to remove multiple spaces and jumpline (break csv)
            var data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ')
            // Escape double-quote with double-double-quote (see https://stackoverflow.com/questions/17808511/properly-escape-a-double-quote-in-csv)
            data = data.replace(/"/g, '""');
            // Push escaped string
            row.push('"' + data + '"');
        }
        csv.push(row.join(separator));
    }
    var csv_string = csv.join('\n');
    var title = document.title.split('|')[0].trim()
    // Download it
    var filename =  title + '_' + table_id.replace('rtable-', '') + '_' + new Date().toLocaleDateString() + '.csv';
    var link = document.createElement('a');
    link.style.display = 'none';
    link.setAttribute('target', '_blank');
    link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv_string));
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function get_url_params() {
    var query = query_params(window.location.href)
    var real = decodeURIComponent(query.query || '')
    return query_params(real)
}

function query_params(str) {
    const queryString = str.indexOf('?') !== -1 ? str.slice(str.indexOf('?') + 1) : str
    const pairs = queryString.split('&')
    const result = {}
    pairs.forEach(function(pair) {
      let [key, val] = pair.split('=')
      if (!key) {
        return
      }
      let isArr = false
      if (key.includes('[]')) {
        key = key.replace('[]', '')
        isArr = true
      }
      val = decodeURIComponent(val || '')
      if (isArr) {
        result[key] = result[key] || []
        result[key].push(val)
      } else {
        result[key] = val
      }
    })
    return JSON.parse(JSON.stringify(result))
  }