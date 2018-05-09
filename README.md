# Wrapper Relation for Contao
This is an extension for Contao that stores a content element's closest wrapper's id in the element's properties. It does so by adding a field `wrapperId` to `tl_content` and using DataContainer callbacks to set/update the relations.

## TODO
If you move a wrapper element from article A to article B, the elements in article A that used to be wrapped by that wrapper retain their now-obsolete `wrapperId`. This is because the `oncut_callback` is provided no information about the element's previous position that would be required to update the relations there. A workaround for this has yet to be implemented.
