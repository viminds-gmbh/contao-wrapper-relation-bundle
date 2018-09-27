# Wrapper Relation for Contao
This is an extension for Contao that stores a content element's closest wrapper's id in the element's properties. It does so by adding a field `wrapperId` to `tl_content` and using DataContainer callbacks to set/update the relations.
