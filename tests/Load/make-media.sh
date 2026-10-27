#!/bin/bash

# creates the media of the load test in tests/Load/media: 10 pictures and 3 videos with about 50 MB each.
# The content is random, so every run gives other checksums and the mediapool stores every file anew.

set -e
cd "$(dirname "$0")"
mkdir -p media

for i in $(seq 1 10); do
	magick -size 3840x2160 plasma:fractal -quality 95 "media/picture-$i.jpg"
done

for i in 1 2 3; do
	# 60 s with 6.6 Mbit/s are about 50 MB, the noise keeps the encoder from going below the bitrate
	ffmpeg -loglevel error -y -f lavfi -i "testsrc2=size=1920x1080:rate=25,noise=alls=40:allf=t+u" -t 60 \
		-c:v libx264 -b:v 6600k -minrate 6600k -maxrate 6600k -bufsize 6600k -pix_fmt yuv420p "media/video-$i.mp4"
done

ls -lh media
