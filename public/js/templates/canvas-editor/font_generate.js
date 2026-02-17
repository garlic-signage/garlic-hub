// config section relative to
let server_font_dir       = __dirname + "/../../resources/fonts/";
let www_font_dir          = "/resources/fonts/";
let save_preview_filepath = __dirname + "/fonts_preview.js";
let save_css_filepath     = __dirname + "/../../css/templates/fonts.css";

// =====================================================================================================
let fs         = require("fs");
let woff2      = require('woff2');
const opentype = require('opentype.js');

console.log(opentype)
let fontsList = [];
const getDirectories = (source) =>
    fs
        .readdirSync(source, {
            withFileTypes: true,
        })
        .filter((dirent) => dirent.isDirectory())
        .map((dirent) => dirent.name);

getDirectories(server_font_dir).forEach((directory) => {
    fontsList.push({
        name: directory,
        files: fs
            .readdirSync(server_font_dir + `${directory}`, {
                withFileTypes: true,
            })
            .map((dirent) => dirent.name),
    });
});

let fontsOutput = [];
let cssOutput = "";
fontsList.forEach((list) => {
    list.files.forEach((file) => {
        if (file !== ".DS_Store") {

            let display_name   = file.substring(0,  file.lastIndexOf("."));
            let url            = www_font_dir + list.name + "/" + file;
            let font_extension = url.slice((url.lastIndexOf(".") - 1 >>> 0) + 2);

            let font;
            if (font_extension === "woff2" )
            {

                let buf =  woff2.decode(fs.readFileSync(server_font_dir + `${list.name}/${file}`));
                font    = opentype.parse(Uint8Array.from(buf).buffer);
                /*
                // This make sense to check if the woff2 font can converted in a correct ttf.
                // Some extremely reduced fonts from google does not.
                let input = fs.readFileSync(server_font_dir + `${list.name}/${file}`);
                let output = server_font_dir + 'output.ttf';
                fs.writeFileSync(output, woff2.decode(input));
                font = opentype.loadSync(output);
            */
            }
            else
            {
                font = opentype.loadSync(server_font_dir + `${list.name}/${file}`);
            }

            let path           = font.getPath(display_name, 0, 0, 32);
            let bbox           = path.getBoundingBox();
            let viewbox        = `${bbox.x1} ${bbox.y1} ${Math.abs(bbox.x2 - bbox.x1)} ${Math.abs(bbox.y2 - bbox.y1)}`;
            let svg_preview    = `<svg xmlns="http://www.w3.org/2000/svg" viewbox="${viewbox}"> <path fill="currentColor" d="${path.toPathData()}" /></svg>`

            console.log(font_extension + ": \t\t" + url)
            fontsOutput.push({
                name: display_name,
                url: url,
                preview: svg_preview,
                loaded: false
            });

            let font_format;
            switch (font_extension)
            {
                case "woff":
                    font_format = "woff";
                    break;
                case "woff2":
                    font_format = "woff2";
                    break;
                default:
                    font_format = "truetype";
                    break;
            }

            // this is for a fonts.css file which could be useful some days
            cssOutput += "@font-face {" +
                "font-family: '" + display_name +"';" +
                "font-style: normal;" +
                "font-weight: normal;" +
                "src: url('" + url + "') format('" + font_format + "');}";
        }
    });


});

fs.writeFile(save_preview_filepath, "let FontsList = " + JSON.stringify(fontsOutput), function (err) {
    if (err) return console.log(err);
    console.log("Done, " + save_preview_filepath + " file generated");
});


fs.writeFile(save_css_filepath, cssOutput, function (err) {
    if (err) return console.log(err);
    console.log("Done, " + save_css_filepath + " file generated");
});
