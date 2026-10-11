Deliberate violations for `tests/Feature/ArchitectureTest.php`. The tests read these files as
text and pass them to the rules under a made-up project path. They are never loaded, and the
real rules only scan `app/` and `routes/`, so these never fail the build. A test fails if a
rule stops finding the violations in here.
