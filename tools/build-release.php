<?php
/**
 * Deprecated compatibility entry point.
 *
 * File 21 has one release authority only: tools/build-release.py. Keeping a
 * second ZIP implementation here would reintroduce manifest and byte-drift.
 */
fwrite( STDERR, "Use: python3 tools/build-release.py\n" );
exit( 64 );
