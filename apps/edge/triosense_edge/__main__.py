"""Allow `python -m triosense_edge.simulate` in addition to the Poetry script."""

from triosense_edge.simulate import main

if __name__ == '__main__':
    raise SystemExit(main())
