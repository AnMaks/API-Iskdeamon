FROM ubuntu:16.04

ENV DEBIAN_FRONTEND=noninteractive

# 1 
RUN printf 'Acquire::ForceIPv4 "true";\n' > /etc/apt/apt.conf.d/99force-ipv4

# 2
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    git \
    swig \
    pkg-config \
    python2.7 \
    python-dev \
    python-setuptools \
    python-pip \
    python-twisted-web \
    imagemagick \
    libmagickcore-dev \
    libmagickwand-dev \
    libmagick++-dev \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# 3
RUN pip install --no-cache-dir simplejson fpconst SOAPpy || true

# 4
WORKDIR /opt/iskdaemon
RUN git clone --depth 1 https://github.com/ricardocabral/iskdaemon.git src


WORKDIR /opt/iskdaemon/src/src

# 5
RUN rm -f test/data/bla.gif

# 6 PATCH setup.py:
RUN python2 - <<'PY'
# -*- coding: utf-8 -*-
import io, re, subprocess

p = "setup.py"
s = io.open(p, "r", encoding="utf-8", errors="ignore").read()

cflags = subprocess.check_output("pkg-config --cflags MagickCore MagickWand", shell=True).decode("utf-8").split()
libs   = subprocess.check_output("pkg-config --libs   MagickCore MagickWand", shell=True).decode("utf-8").split()

inc_dirs = [x[2:] for x in cflags if x.startswith("-I")]
lib_dirs = [x[2:] for x in libs   if x.startswith("-L")]
lib_names= [x[2:] for x in libs   if x.startswith("-l")]

def do_sub(pattern, repl):
    global s
    s2, n = re.subn(pattern, repl, s, flags=re.M)
    if n:
        s = s2


do_sub(r'(^\s*include_dirs\s*=\s*)include_dirs(\s*,)',
       r'\1%r\2' % inc_dirs)


do_sub(r'(^\s*library_dirs\s*=\s*)library_dirs(\s*,)',
       r'\1%r\2' % lib_dirs)


do_sub(r'(^\s*libraries\s*=\s*)libraries(\s*,)',
       r'\1%r\2' % lib_names)

io.open(p, "w", encoding="utf-8").write(s)

print("PATCHED setup.py")
print("include_dirs =", inc_dirs)
print("library_dirs =", lib_dirs)
print("libraries    =", lib_names)
PY

# 7
RUN python2 setup.py build_ext --inplace && \
    python2 -c "import _imgdb; print('OK: _imgdb imported')"

EXPOSE 31128

# 8
VOLUME ["/opt/iskdaemon/src/src/data"]

# 9
CMD ["bash", "-lc", "cd /opt/iskdaemon/src/src && python2 iskdaemon.py -q"]
